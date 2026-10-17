<?php

namespace App\Domain\Payments;

use App\Domain\Money\Exact;
use App\Models\Document;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Support\SafeAccess;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Tracks due / partial / completed collection on posted sales invoices.
 */
final class SaleCollectionStatus
{
    public const DUE = 'due';

    public const PARTIAL = 'partial';

    public const COMPLETED = 'completed';

    public function sync(Document $document): void
    {
        if (! $document->isSale() || ! $document->isPosted() || ! $this->columnsReady()) {
            return;
        }

        if ($document->collection_status === self::COMPLETED && $document->collection_confirmed_at !== null) {
            return;
        }

        $remaining = $this->remainingAmount($document);
        $allocated = Exact::of($document->net_total)->minus($remaining);

        if ($allocated->isZero()) {
            $document->collection_status = self::DUE;
            $document->collection_confirmed_at = null;
        } elseif ($remaining->isPositive()) {
            $document->collection_status = self::PARTIAL;
            $document->collection_confirmed_at = null;
        } else {
            $document->collection_status = self::PARTIAL;
        }

        $document->saveQuietly();
    }

    public function confirmCollected(Document $document, User $by): Document
    {
        abort_unless($document->isSale() && $document->isPosted(), 404);
        abort_unless($this->columnsReady(), 503, __('Run migrations on the Maintenance page first.'));

        $remaining = $this->remainingAmount($document);
        if ($remaining->isPositive()) {
            throw ValidationException::withMessages([
                'collection' => __('This invoice still has an outstanding balance.'),
            ]);
        }

        $document->forceFill([
            'collection_status' => self::COMPLETED,
            'collection_confirmed_at' => Carbon::now(),
            'updated_by' => $by->id,
        ])->save();

        return $document->fresh();
    }

    /**
     * @return Collection<int, Document>
     */
    public function pendingForCollector(int $limit = 100): Collection
    {
        $query = Document::query()
            ->where('type', Document::TYPE_SALE)
            ->where('status', Document::STATUS_POSTED);

        if ($this->columnsReady()) {
            $query->where(function ($q) {
                $q->whereIn('collection_status', [self::DUE, self::PARTIAL])
                    ->orWhereNull('collection_status');
            })->where(function ($q) {
                $q->whereNull('collection_confirmed_at')
                    ->orWhere('collection_status', '!=', self::COMPLETED);
            });
        }

        return $query
            ->orderByDesc('doc_date')
            ->orderByDesc('id')
            ->with('retailer')
            ->limit($limit)
            ->get()
            ->filter(fn (Document $doc) => $this->remainingAmount($doc)->isPositive()
                || ($this->columnsReady()
                    && $this->remainingAmount($doc)->isZero()
                    && $doc->collection_status !== self::COMPLETED))
            ->values();
    }

    public function columnsReady(): bool
    {
        return SafeAccess::hasColumn('documents', 'collection_status');
    }

    public function remainingAmount(Document $document): BigDecimal
    {
        $allocated = Exact::of(
            PaymentAllocation::query()
                ->where('document_id', $document->id)
                ->whereHas('payment', fn ($q) => $q->where('status', Payment::STATUS_POSTED))
                ->sum('amount')
        );

        return Exact::of($document->net_total)->minus($allocated);
    }

    public function label(?string $status): string
    {
        return match ($status) {
            self::PARTIAL => __('Partially due'),
            self::COMPLETED => __('Completed'),
            default => __('Due'),
        };
    }
}
