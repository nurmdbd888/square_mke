<?php

namespace App\Domain\Returns;

use App\Domain\Costing\Recompute;
use App\Domain\Money\Exact;
use App\Models\DocumentLine;
use App\Models\ProductBatch;
use App\Models\StockNote;
use App\Models\StockNoteLine;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates, updates, posts and voids returns, adjustments and stock counts.
 */
final class StockNoteWriter
{
    public function __construct(private readonly Recompute $recompute) {}

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $lines
     */
    public function save(string $type, array $header, array $lines, ?User $by = null, ?StockNote $existing = null): StockNote
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('Add at least one line.')]);
        }

        return DB::transaction(function () use ($type, $header, $lines, $by, $existing) {
            $note = $existing ?? new StockNote(['type' => $type, 'status' => StockNote::STATUS_DRAFT]);

            if ($existing !== null && $existing->isVoid()) {
                throw ValidationException::withMessages(['status' => __('A void document cannot be edited.')]);
            }

            $wasPosted = $existing?->isPosted() ?? false;
            $oldDate = $existing?->doc_date?->toDateString();
            $affected = $existing ? $existing->lines()->pluck('product_id')->all() : [];

            $net = Exact::of('0');
            foreach ($lines as $i => $line) {
                $lineValue = Exact::of($line['line_value'] ?? $this->defaultLineValue($line));
                $net = $net->plus($lineValue);
                $lines[$i]['line_value'] = Exact::money($lineValue);
            }

            $note->fill([
                'type' => $type,
                'number' => trim((string) $header['number']),
                'doc_date' => Carbon::parse($header['doc_date'])->toDateString(),
                'retailer_id' => $header['retailer_id'] ?? null,
                'supplier_id' => $header['supplier_id'] ?? null,
                'source_document_id' => $header['source_document_id'] ?? null,
                'net_total' => Exact::money($net),
                'notes' => isset($header['notes']) ? (string) $header['notes'] : null,
                'updated_by' => $by?->id,
            ]);

            if (! $note->exists) {
                $note->created_by = $by?->id;
                $note->status = StockNote::STATUS_DRAFT;
            }

            $note->save();
            $note->lines()->delete();

            foreach ($lines as $i => $line) {
                $restore = null;
                $lossCost = null;
                $linkedId = $line['linked_document_line_id'] ?? null;
                $condition = (string) ($line['line_condition'] ?? StockNoteLine::CONDITION_GOOD);

                if ($linkedId && $type === StockNote::TYPE_SALES_RETURN) {
                    $docLine = DocumentLine::query()->find($linkedId);
                    if ($docLine !== null && Exact::of($docLine->qty)->isPositive()) {
                        $unitCost = Exact::of($docLine->issue_cost)->dividedBy(Exact::of($docLine->qty), 6);
                        $qty = Exact::of($line['qty'] ?? 0);
                        if ($condition === StockNoteLine::CONDITION_GOOD) {
                            $restore = Exact::money($unitCost->multipliedBy($qty));
                        } else {
                            $lossCost = Exact::money($unitCost->multipliedBy($qty));
                        }
                    }
                }

                $batchId = $line['product_batch_id'] ?? null;
                if (! $batchId && ! empty($line['batch_no'])) {
                    $batch = ProductBatch::query()->firstOrCreate(
                        [
                            'product_id' => (int) $line['product_id'],
                            'batch_no' => (string) $line['batch_no'],
                        ],
                        ['expiry_date' => empty($line['expiry_date']) ? null : Carbon::parse($line['expiry_date'])->toDateString()],
                    );
                    $batchId = $batch->id;
                }

                StockNoteLine::query()->create([
                    'stock_note_id' => $note->id,
                    'line_no' => $i + 1,
                    'product_id' => (int) $line['product_id'],
                    'product_batch_id' => $batchId,
                    'line_condition' => $line['line_condition'] ?? StockNoteLine::CONDITION_GOOD,
                    'qty' => Exact::qty($line['qty'] ?? 0),
                    'expected_qty' => isset($line['expected_qty']) ? Exact::qty($line['expected_qty']) : null,
                    'unit_price' => Exact::price($line['unit_price'] ?? 0),
                    'line_value' => $line['line_value'],
                    'linked_document_line_id' => $linkedId,
                    'restore_cost' => $restore,
                    'loss_cost' => $lossCost,
                    'notes' => isset($line['notes']) ? (string) $line['notes'] : null,
                ]);
            }

            $lossTotal = Exact::of('0');
            foreach ($note->lines()->get() as $savedLine) {
                if ($savedLine->loss_cost !== null) {
                    $lossTotal = $lossTotal->plus(Exact::of($savedLine->loss_cost));
                }
            }
            $note->stock_loss_total = Exact::money($lossTotal);
            $note->save();

            $note->load('lines');
            $action = (string) ($header['action'] ?? 'save');

            if ($action === 'post' && ! $wasPosted) {
                $this->post($note->fresh('lines'), $by);
            } elseif ($wasPosted) {
                $this->post($note->fresh('lines'), $by, quiet: true);
                $affected = array_values(array_unique(array_merge(
                    $affected,
                    $note->lines()->pluck('product_id')->all(),
                )));
                $from = $oldDate !== null && $oldDate < $note->doc_date->toDateString()
                    ? $oldDate
                    : $note->doc_date->toDateString();
                $this->recompute->products($affected, $from);
                AuditLogger::log('stock_note.posted_edited', $note, null, [
                    'number' => $note->number,
                    'status' => StockNote::STATUS_POSTED,
                ], $by);
            }

            return $note->fresh(['lines', 'retailer']);
        });
    }

    public function post(StockNote $note, ?User $by = null, bool $quiet = false): StockNote
    {
        if ($note->isVoid()) {
            throw ValidationException::withMessages(['status' => __('A void document cannot be posted.')]);
        }

        if ($note->lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('Add at least one line.')]);
        }

        $this->validateLinks($note);

        $note->status = StockNote::STATUS_POSTED;
        $note->posted_at = $note->posted_at ?? now();
        $note->voided_at = null;
        $note->updated_by = $by?->id;
        $note->save();

        if (! $quiet) {
            AuditLogger::log('stock_note.posted', $note, null, [
                'number' => $note->number,
                'status' => StockNote::STATUS_POSTED,
            ], $by);
            $this->recompute->products(
                $note->lines->pluck('product_id')->all(),
                $note->doc_date->toDateString(),
            );
        }

        return $note->fresh('lines');
    }

    public function void(StockNote $note, ?User $by = null): StockNote
    {
        if ($note->isVoid()) {
            return $note;
        }

        if ($note->isDraft()) {
            $note->delete();

            return $note;
        }

        $from = $note->doc_date->toDateString();
        $products = $note->lines()->pluck('product_id')->all();

        $note->forceFill([
            'status' => StockNote::STATUS_VOID,
            'voided_at' => now(),
            'updated_by' => $by?->id,
            'stock_loss_total' => '0.00',
        ])->save();

        AuditLogger::log('stock_note.voided', $note, ['status' => StockNote::STATUS_POSTED], [
            'status' => StockNote::STATUS_VOID,
        ], $by);

        $this->recompute->products($products, $from);

        return $note->fresh();
    }

    private function validateLinks(StockNote $note): void
    {
        if ($note->type !== StockNote::TYPE_SALES_RETURN) {
            return;
        }

        foreach ($note->lines as $line) {
            if ($line->linked_document_line_id === null) {
                continue;
            }

            $saleLine = DocumentLine::query()->with('document')->find($line->linked_document_line_id);
            if ($saleLine === null || ! $saleLine->document->isSale() || ! $saleLine->document->isPosted()) {
                throw ValidationException::withMessages([
                    'lines' => __('Linked sale line is missing or not posted.'),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function defaultLineValue(array $line): string
    {
        $qty = Exact::of($line['qty'] ?? 0);
        $price = Exact::of($line['unit_price'] ?? 0);

        return Exact::money($qty->multipliedBy($price));
    }
}
