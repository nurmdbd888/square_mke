<?php

namespace Tests\Feature\Documents;

use App\Domain\Costing\Recompute;
use App\Domain\Documents\DocumentWriter;
use App\Domain\Money\Exact;
use App\Models\Document;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Incremental recompute after random edits equals a full rebuild (to the paisa).
 */
class CostingPropertyTest extends TestCase
{
    use RefreshDatabase;

    public function test_five_hundred_random_sequences_match_full_rebuild(): void
    {
        mt_srand(20261017);

        $owner = $this->owner();
        $products = collect(range(1, 5))->map(fn ($i) => Product::query()->create([
            'code' => 'P'.$i,
            'name_en' => 'Product '.$i,
            'unit' => 'Pcs',
            'buy_price' => (string) (10 + $i),
        ]));
        $supplier = Supplier::query()->create(['name' => 'Square']);
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $writer = app(DocumentWriter::class);
        $recompute = app(Recompute::class);

        for ($i = 0; $i < 500; $i++) {
            $product = $products[$i % $products->count()];
            $day = sprintf('2026-%02d-%02d', 1 + ($i % 9), 1 + ($i % 27));
            $roll = mt_rand(0, 9);

            if ($roll <= 3) {
                $qty = (string) mt_rand(1, 20);
                $price = (string) (10 + ($i % 5));
                $writer->save('purchase', [
                    'number' => 'RP-'.$i,
                    'supplier_id' => $supplier->id,
                    'doc_date' => $day,
                    'action' => 'post',
                ], [[
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'unit_price' => $price,
                    'line_value' => Exact::money(Exact::of($qty)->multipliedBy(Exact::of($price))),
                ]], $owner);
            } elseif ($roll <= 7) {
                $qty = (string) mt_rand(1, 8);
                $bonus = $roll === 7 ? '1' : '0';
                $writer->save('sale', [
                    'number' => 'RS-'.$i,
                    'retailer_id' => $retailer->id,
                    'doc_date' => $day,
                    'action' => 'post',
                ], [[
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'bonus_qty' => $bonus,
                    'unit_price' => '15',
                    'line_value' => Exact::money(Exact::of($qty)->multipliedBy(Exact::of('15'))),
                ]], $owner);
            } else {
                $victim = Document::query()->where('status', Document::STATUS_POSTED)->inRandomOrder()->first();
                if ($victim !== null) {
                    if ($roll === 8) {
                        $writer->void($victim, $owner);
                    } else {
                        $line = $victim->lines()->first();
                        if ($line !== null) {
                            $newQty = Exact::qty(Exact::of($line->qty)->plus('1'));
                            $writer->save($victim->type, [
                                'number' => $victim->number,
                                'supplier_id' => $victim->supplier_id,
                                'retailer_id' => $victim->retailer_id,
                                'doc_date' => $victim->doc_date->toDateString(),
                                'header_discount' => $victim->header_discount,
                                'action' => 'post',
                            ], [[
                                'product_id' => $line->product_id,
                                'qty' => $newQty,
                                'bonus_qty' => $line->bonus_qty,
                                'unit_price' => $line->unit_price,
                                'line_value' => Exact::money(Exact::of($newQty)->multipliedBy(Exact::of($line->unit_price))),
                                'line_discount' => $line->line_discount,
                                'sd_amount' => $line->sd_amount,
                                'vat_amount' => $line->vat_amount,
                            ]], $owner, $victim);
                        }
                    }
                }
            }
        }

        $snapshot = [];
        foreach ($products as $product) {
            $snapshot[$product->id] = StockLedgerEntry::query()
                ->where('product_id', $product->id)
                ->orderBy('doc_date')
                ->orderBy('rank')
                ->orderBy('posted_at')
                ->orderBy('id')
                ->get(['doc_date', 'movement', 'qty_in', 'qty_out', 'value_in', 'value_out', 'running_qty', 'running_value', 'average_cost'])
                ->map(fn ($r) => [
                    'doc_date' => $r->doc_date->toDateString(),
                    'movement' => $r->movement,
                    'qty_in' => Exact::qty($r->qty_in),
                    'qty_out' => Exact::qty($r->qty_out),
                    'value_in' => Exact::money($r->value_in),
                    'value_out' => Exact::money($r->value_out),
                    'running_qty' => Exact::qty($r->running_qty),
                    'running_value' => Exact::money($r->running_value),
                    'average_cost' => (string) $r->average_cost,
                ])
                ->all();
        }

        $recompute->all();

        foreach ($products as $product) {
            $full = StockLedgerEntry::query()
                ->where('product_id', $product->id)
                ->orderBy('doc_date')
                ->orderBy('rank')
                ->orderBy('posted_at')
                ->orderBy('id')
                ->get(['doc_date', 'movement', 'qty_in', 'qty_out', 'value_in', 'value_out', 'running_qty', 'running_value', 'average_cost'])
                ->map(fn ($r) => [
                    'doc_date' => $r->doc_date->toDateString(),
                    'movement' => $r->movement,
                    'qty_in' => Exact::qty($r->qty_in),
                    'qty_out' => Exact::qty($r->qty_out),
                    'value_in' => Exact::money($r->value_in),
                    'value_out' => Exact::money($r->value_out),
                    'running_qty' => Exact::qty($r->running_qty),
                    'running_value' => Exact::money($r->running_value),
                    'average_cost' => (string) $r->average_cost,
                ])
                ->all();

            $this->assertSame($full, $snapshot[$product->id], 'Product '.$product->code.' drifted');
        }
    }
}
