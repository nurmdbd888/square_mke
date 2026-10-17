<?php

namespace App\Domain\Payments;

use App\Domain\Money\Exact;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Age open invoices into 0–30 / 31–60 / 61–90 / 90+ day buckets.
 */
final class Ageing
{
    public const BUCKETS = ['0-30', '31-60', '61-90', '90+'];

    public function __construct(private readonly PartyBalance $balances) {}

    /**
     * @return array{buckets: array<string, string>, total: string, rows: list<array{document_id: int, number: string, doc_date: string, days: int, bucket: string, remaining: string}>}
     */
    public function forRetailer(int $retailerId, CarbonInterface|string|null $asOf = null): array
    {
        return $this->build($this->balances->openInvoices('retailer', $retailerId, $asOf), $asOf);
    }

    /**
     * @return array{buckets: array<string, string>, total: string, rows: list<array{document_id: int, number: string, doc_date: string, days: int, bucket: string, remaining: string}>}
     */
    public function forSupplier(int $supplierId, CarbonInterface|string|null $asOf = null): array
    {
        return $this->build($this->balances->openInvoices('supplier', $supplierId, $asOf), $asOf);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $invoices
     * @return array{buckets: array<string, string>, total: string, rows: list<array{document_id: int, number: string, doc_date: string, days: int, bucket: string, remaining: string}>}
     */
    private function build($invoices, CarbonInterface|string|null $asOf): array
    {
        $asOfDate = ($asOf === null ? now() : Carbon::parse($asOf))->startOfDay();
        $buckets = array_fill_keys(self::BUCKETS, Exact::of('0'));
        $rows = [];

        foreach ($invoices as $invoice) {
            $days = Carbon::parse($invoice->doc_date)->startOfDay()->diffInDays($asOfDate);
            $bucket = match (true) {
                $days <= 30 => '0-30',
                $days <= 60 => '31-60',
                $days <= 90 => '61-90',
                default => '90+',
            };
            $remaining = Exact::of($invoice->remaining);
            $buckets[$bucket] = $buckets[$bucket]->plus($remaining);
            $rows[] = [
                'document_id' => $invoice->document_id,
                'number' => $invoice->number,
                'doc_date' => $invoice->doc_date,
                'days' => (int) $days,
                'bucket' => $bucket,
                'remaining' => Exact::money($remaining),
            ];
        }

        $total = Exact::of('0');
        $plain = [];
        foreach ($buckets as $key => $value) {
            $plain[$key] = Exact::money($value);
            $total = $total->plus($value);
        }

        return [
            'buckets' => $plain,
            'total' => Exact::money($total),
            'rows' => $rows,
        ];
    }
}
