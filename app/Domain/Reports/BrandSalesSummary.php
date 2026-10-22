<?php

namespace App\Domain\Reports;

use App\Domain\Money\Exact;
use App\Models\Document;
use App\Models\DocumentLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BrandSalesSummary
{
    /**
     * @return Collection<int, array{brand: string, qty: string, bonus_qty: string, line_value: string, invoices: int}>
     */
    public function between(string $from, string $to): Collection
    {
        $rows = DocumentLine::query()
            ->select([
                DB::raw("COALESCE(NULLIF(TRIM(products.brand), ''), '—') as brand"),
                DB::raw('SUM(document_lines.qty) as qty'),
                DB::raw('SUM(document_lines.bonus_qty) as bonus_qty'),
                DB::raw('SUM(document_lines.line_value) as line_value'),
                DB::raw('COUNT(DISTINCT documents.id) as invoices'),
            ])
            ->join('documents', 'documents.id', '=', 'document_lines.document_id')
            ->join('products', 'products.id', '=', 'document_lines.product_id')
            ->where('documents.type', Document::TYPE_SALE)
            ->where('documents.status', Document::STATUS_POSTED)
            ->whereBetween('documents.doc_date', [$from, $to])
            ->groupBy('brand')
            ->orderByDesc('line_value')
            ->get();

        return $rows->map(fn ($row) => [
            'brand' => (string) $row->brand,
            'qty' => Exact::qty(Exact::of($row->qty ?? 0)),
            'bonus_qty' => Exact::qty(Exact::of($row->bonus_qty ?? 0)),
            'line_value' => Exact::money(Exact::of($row->line_value ?? 0)),
            'invoices' => (int) $row->invoices,
        ]);
    }
}
