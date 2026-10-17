<?php

namespace App\Domain\Documents;

use App\Domain\Costing\HeaderDiscountAllocator;
use App\Domain\Costing\PurchaseCost;
use App\Domain\Costing\Recompute;
use App\Domain\Money\Exact;
use App\Domain\Profit\Commission;
use App\Domain\Stock\BatchRegistry;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Product;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates, updates, posts and voids purchase and sales documents.
 */
final class DocumentWriter
{
    public function __construct(
        private readonly DuplicateGuard $duplicates,
        private readonly HeaderDiscountAllocator $allocator,
        private readonly PurchaseCost $purchaseCost,
        private readonly Commission $commission,
        private readonly Recompute $recompute,
        private readonly BatchRegistry $batches,
    ) {}

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $lines
     */
    public function save(string $type, array $header, array $lines, ?User $by = null, ?Document $existing = null): Document
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('Add at least one line.')]);
        }

        return DB::transaction(function () use ($type, $header, $lines, $by, $existing) {
            $document = $existing ?? new Document(['type' => $type, 'status' => Document::STATUS_DRAFT]);

            if ($existing !== null && $existing->isVoid()) {
                throw ValidationException::withMessages(['status' => __('A void document cannot be edited.')]);
            }

            $wasPosted = $existing?->isPosted() ?? false;
            $oldDate = $existing?->doc_date?->toDateString();
            $affected = $existing ? $existing->lines()->pluck('product_id')->all() : [];

            $shares = $this->allocator->allocate($lines, $header['header_discount'] ?? 0);
            $subtotal = Exact::of('0');

            foreach ($lines as $i => $line) {
                $lineValue = Exact::of($line['line_value'] ?? $this->defaultLineValue($line));
                $subtotal = $subtotal->plus($lineValue);
                $lines[$i]['line_value'] = Exact::money($lineValue);
                $lines[$i]['header_discount_share'] = $shares[$i];
            }

            $headerDiscount = Exact::of($header['header_discount'] ?? 0);
            $slab = Exact::of($header['slab_adjustment'] ?? 0);
            $combo = Exact::of($header['combo_bonus_discount'] ?? 0);
            $rounding = Exact::of($header['rounding_adjustment'] ?? 0);
            $net = $subtotal->minus($headerDiscount)->minus($slab)->minus($combo)->plus($rounding);

            $document->fill([
                'type' => $type,
                'number' => trim((string) $header['number']),
                'supplier_id' => $type === Document::TYPE_PURCHASE ? ($header['supplier_id'] ?? null) : null,
                'retailer_id' => $type === Document::TYPE_SALE ? ($header['retailer_id'] ?? null) : null,
                'doc_date' => Carbon::parse($header['doc_date'])->toDateString(),
                'delivery_date' => empty($header['delivery_date']) ? null : Carbon::parse($header['delivery_date'])->toDateString(),
                'sales_rep_id' => $header['sales_rep_id'] ?? null,
                'route_id' => $header['route_id'] ?? null,
                'vehicle_id' => $header['vehicle_id'] ?? null,
                'header_discount' => Exact::money($headerDiscount),
                'slab_adjustment' => Exact::money($slab),
                'combo_bonus_discount' => Exact::money($combo),
                'rounding_adjustment' => Exact::money($rounding),
                'subtotal' => Exact::money($subtotal),
                'net_total' => Exact::money($net),
                'notes' => isset($header['notes']) ? (string) $header['notes'] : null,
                'updated_by' => $by?->id,
            ]);

            if (! $document->exists) {
                $document->created_by = $by?->id;
                $document->status = Document::STATUS_DRAFT;
            }

            $this->duplicates->assertUnique($document);
            $document->save();

            $document->lines()->delete();

            foreach ($lines as $i => $line) {
                $product = Product::query()->find((int) $line['product_id']);
                $batch = $product !== null
                    ? $this->batches->forLine($product, $line['batch_no'] ?? null, $line['expiry_date'] ?? null)
                    : null;

                DocumentLine::query()->create([
                    'document_id' => $document->id,
                    'line_no' => $i + 1,
                    'product_id' => (int) $line['product_id'],
                    'batch_no' => isset($line['batch_no']) ? (string) $line['batch_no'] : null,
                    'expiry_date' => empty($line['expiry_date']) ? null : Carbon::parse($line['expiry_date'])->toDateString(),
                    'product_batch_id' => $batch?->id ?? ($line['product_batch_id'] ?? null),
                    'kind' => ($line['kind'] ?? DocumentLine::KIND_NORMAL) === DocumentLine::KIND_COMBO_BONUS
                        ? DocumentLine::KIND_COMBO_BONUS
                        : DocumentLine::KIND_NORMAL,
                    'qty' => Exact::qty($line['qty'] ?? 0),
                    'bonus_qty' => Exact::qty($line['bonus_qty'] ?? 0),
                    'unit_price' => Exact::price($line['unit_price'] ?? 0),
                    'line_discount' => Exact::money($line['line_discount'] ?? 0),
                    'header_discount_share' => $line['header_discount_share'],
                    'line_value' => $line['line_value'],
                    'sd_pct' => Exact::rate($line['sd_pct'] ?? 0),
                    'vat_pct' => Exact::rate($line['vat_pct'] ?? 0),
                    'sd_amount' => Exact::money($line['sd_amount'] ?? 0),
                    'vat_amount' => Exact::money($line['vat_amount'] ?? 0),
                    'notes' => isset($line['notes']) ? (string) $line['notes'] : null,
                ]);
            }

            $document->load('lines');
            $action = (string) ($header['action'] ?? 'save');

            if ($action === 'post' && ! $wasPosted) {
                $this->post($document->fresh('lines'), $by);
            } elseif ($wasPosted) {
                $this->post($document->fresh('lines'), $by, quiet: true);
                $affected = array_values(array_unique(array_merge(
                    $affected,
                    $document->lines()->pluck('product_id')->all(),
                )));
                $from = $oldDate !== null && $oldDate < $document->doc_date->toDateString()
                    ? $oldDate
                    : $document->doc_date->toDateString();
                $this->recompute->products($affected, $from);
                AuditLogger::log('document.posted_edited', $document, null, [
                    'number' => $document->number,
                    'status' => Document::STATUS_POSTED,
                ], $by);
            }

            return $document->fresh(['lines', 'supplier', 'retailer']);
        });
    }

    public function post(Document $document, ?User $by = null, bool $quiet = false): Document
    {
        if ($document->isVoid()) {
            throw ValidationException::withMessages(['status' => __('A void document cannot be posted.')]);
        }

        if ($document->lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('Add at least one line.')]);
        }

        $this->duplicates->assertUnique($document);

        $document->status = Document::STATUS_POSTED;
        $document->posted_at = $document->posted_at ?? now();
        $document->voided_at = null;
        $document->updated_by = $by?->id;

        if ($document->isSale()) {
            $this->fillSaleTotals($document);
        }

        $document->save();

        if (! $quiet) {
            AuditLogger::log('document.posted', $document, null, [
                'number' => $document->number,
                'status' => Document::STATUS_POSTED,
            ], $by);
            $this->recompute->products(
                $document->lines->pluck('product_id')->all(),
                $document->doc_date->toDateString(),
            );
        }

        return $document->fresh(['lines']);
    }

    public function void(Document $document, ?User $by = null): Document
    {
        if ($document->isVoid()) {
            return $document;
        }

        if ($document->isDraft()) {
            $document->delete();

            return $document;
        }

        $from = $document->doc_date->toDateString();
        $products = $document->lines()->pluck('product_id')->all();

        $document->forceFill([
            'status' => Document::STATUS_VOID,
            'voided_at' => now(),
            'updated_by' => $by?->id,
            'revenue' => null,
            'cogs' => null,
            'free_goods_cost' => null,
            'commission_gross' => null,
            'commission_vat' => null,
            'commission_net' => null,
        ])->save();

        AuditLogger::log('document.voided', $document, ['status' => Document::STATUS_POSTED], [
            'status' => Document::STATUS_VOID,
        ], $by);

        $this->recompute->products($products, $from);

        return $document->fresh();
    }

    private function fillSaleTotals(Document $document): void
    {
        $revenue = Exact::of('0');

        foreach ($document->lines as $line) {
            if ($line->isComboBonus()) {
                continue;
            }

            $lineRevenue = Exact::of($line->qty)
                ->multipliedBy(Exact::of($line->unit_price))
                ->minus(Exact::of($line->line_discount))
                ->minus(Exact::of($line->header_discount_share));
            $revenue = $revenue->plus($lineRevenue);
        }

        $revenue = $revenue
            ->minus(Exact::of($document->slab_adjustment))
            ->minus(Exact::of($document->combo_bonus_discount))
            ->plus(Exact::of($document->rounding_adjustment));

        $commission = $this->commission->forSale(
            Exact::money($revenue),
            Exact::money($document->subtotal),
            $document->doc_date,
        );

        $document->revenue = Exact::money($revenue);
        $document->commission_gross = $commission['gross'] ?? null;
        $document->commission_vat = $commission['vat'] ?? null;
        $document->commission_net = $commission['net'] ?? null;
        // cogs / free_goods filled by Recompute after ledger walk
        $document->cogs = $document->cogs ?? '0.00';
        $document->free_goods_cost = $document->free_goods_cost ?? '0.00';
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function defaultLineValue(array $line): string
    {
        $qty = Exact::of($line['qty'] ?? 0);
        $price = Exact::of($line['unit_price'] ?? 0);

        return Exact::money($qty->multipliedBy($price)->minus(Exact::of($line['line_discount'] ?? 0)));
    }
}
