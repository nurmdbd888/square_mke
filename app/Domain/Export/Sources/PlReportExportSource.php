<?php

namespace App\Domain\Export\Sources;

use App\Domain\Export\ExportSource;
use App\Domain\Profit\Summary;

final class PlReportExportSource implements ExportSource
{
    public function __construct(private readonly Summary $summary) {}

    public function allColumns(): array
    {
        return ['line', 'amount'];
    }

    public function defaultColumns(): array
    {
        return $this->allColumns();
    }

    public function rows(array $filters, array $columns): array
    {
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->endOfMonth()->toDateString();
        $businessDaysOnly = filter_var($filters['business_days'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $figures = $this->summary->between($from, $to, $businessDaysOnly);

        $headers = ['Line', 'Amount'];
        $lines = [
            ['Revenue', $figures['revenue']],
            ['COGS', $figures['cogs']],
            ['Free goods cost', $figures['free_goods']],
            ['Gross profit', $figures['gross']],
            ['Commission (net)', $figures['commission_net']],
            ['Profit before expenses', $figures['profit_before_expenses']],
            ['Operating expenses', $figures['expenses']],
            ['Stock loss', $figures['stock_loss']],
            ['Net profit', $figures['net_profit']],
        ];

        return ['headers' => $headers, 'rows' => $lines];
    }
}
