<?php

namespace App\Domain\Payments;

use App\Domain\Money\Exact;
use App\Models\Document;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Support\Access\PaymentAccess;
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
     * Days after invoice date before a still-unpaid sale is overdue (settings).
     */
    public function dueDays(): int
    {
        $days = (int) (setting('reminder_due_days') ?? 30);

        return max(1, $days);
    }

    public function isOverdue(Document $document): bool
    {
        if (! $document->isSale() || ! $document->isPosted() || $document->doc_date === null) {
            return false;
        }

        if (! $this->remainingAmount($document)->isPositive()) {
            return false;
        }

        return $document->doc_date->lt(Carbon::today()->subDays($this->dueDays()));
    }

    /**
     * @param  array{limit?: int, status?: string|null, overdue?: bool, q?: string|null}  $filters
     * @return Collection<int, Document>
     */
    public function pendingForCollector(int|array $limitOrFilters = 100): Collection
    {
        $filters = is_array($limitOrFilters)
            ? $limitOrFilters
            : ['limit' => $limitOrFilters];

        $limit = max(1, min(500, (int) ($filters['limit'] ?? 150)));
        $status = $filters['status'] ?? null;
        $overdueOnly = (bool) ($filters['overdue'] ?? false);
        $q = trim((string) ($filters['q'] ?? ''));

        $query = Document::query()
            ->where('type', Document::TYPE_SALE)
            ->where('status', Document::STATUS_POSTED);

        if ($this->columnsReady()) {
            $query->where(function ($w) {
                $w->whereIn('collection_status', [self::DUE, self::PARTIAL])
                    ->orWhereNull('collection_status');
            })->where(function ($w) {
                $w->whereNull('collection_confirmed_at')
                    ->orWhere('collection_status', '!=', self::COMPLETED);
            });
        }

        if (in_array($status, [self::DUE, self::PARTIAL], true)) {
            if ($status === self::DUE) {
                $query->where(function ($w) {
                    $w->where('collection_status', self::DUE)->orWhereNull('collection_status');
                });
            } else {
                $query->where('collection_status', self::PARTIAL);
            }
        }

        if ($overdueOnly) {
            $query->whereDate('doc_date', '<', Carbon::today()->subDays($this->dueDays()));
        }

        if ($q !== '') {
            $like = '%'.addcslashes($q, '\\%_').'%';
            $query->where(function ($w) use ($like) {
                $w->where('number', 'like', $like)
                    ->orWhereHas('retailer', fn ($r) => $r->where('name', 'like', $like)->orWhere('code', 'like', $like));
            });
        }

        $rows = $query
            ->orderBy('doc_date')
            ->orderBy('id')
            ->with('retailer')
            ->limit($limit * 2)
            ->get()
            ->filter(function (Document $doc) use ($status, $overdueOnly) {
                $remaining = $this->remainingAmount($doc);
                $keep = $remaining->isPositive()
                    || ($this->columnsReady()
                        && $remaining->isZero()
                        && $doc->collection_status !== self::COMPLETED);

                if (! $keep) {
                    return false;
                }

                if ($status === 'ready') {
                    return $remaining->isZero();
                }

                if ($overdueOnly && ! $this->isOverdue($doc)) {
                    return false;
                }

                return true;
            })
            ->values();

        // Overdue unpaid first, then ready-to-confirm, then newest among the rest.
        return $rows
            ->sortBy(function (Document $doc) {
                $remaining = $this->remainingAmount($doc);
                $overdue = $remaining->isPositive() && $this->isOverdue($doc) ? 0 : 1;
                $ready = $remaining->isZero() ? 0 : 1;

                return sprintf('%d-%d-%s-%010d', $overdue, $ready, $doc->doc_date?->format('Y-m-d') ?? '9999', $doc->id);
            })
            ->take($limit)
            ->values();
    }

    /**
     * Daily field summary for the Collections page.
     *
     * @return array{receipt_count: int, receipt_total: string, confirmed_today: int, overdue_count: int, partial_open: int, ready_count: int}
     */
    public function todaySummary(?User $user = null): array
    {
        $receiptQuery = Payment::query()
            ->where('kind', Payment::KIND_RECEIPT)
            ->where('status', Payment::STATUS_POSTED)
            ->whereDate('payment_date', Carbon::today());

        if ($user !== null && ! PaymentAccess::canManage($user)) {
            $receiptQuery->where('created_by', $user->id);
        }

        $receiptCount = (clone $receiptQuery)->count();
        $receiptTotal = (string) (clone $receiptQuery)->sum('amount');

        $confirmedToday = 0;
        if ($this->columnsReady()) {
            $confirmedToday = Document::query()
                ->where('type', Document::TYPE_SALE)
                ->where('collection_status', self::COMPLETED)
                ->whereNotNull('collection_confirmed_at')
                ->whereDate('collection_confirmed_at', Carbon::today())
                ->count();
        }

        $pending = $this->pendingForCollector(['limit' => 200]);
        $overdueCount = 0;
        $partialOpen = 0;
        $readyCount = 0;
        foreach ($pending as $doc) {
            $remaining = $this->remainingAmount($doc);
            if ($remaining->isZero()) {
                $readyCount++;
            } elseif ($this->isOverdue($doc)) {
                $overdueCount++;
            }
            if ($doc->collection_status === self::PARTIAL && $remaining->isPositive()) {
                $partialOpen++;
            }
        }

        return [
            'receipt_count' => $receiptCount,
            'receipt_total' => $receiptTotal,
            'confirmed_today' => $confirmedToday,
            'overdue_count' => $overdueCount,
            'partial_open' => $partialOpen,
            'ready_count' => $readyCount,
        ];
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
