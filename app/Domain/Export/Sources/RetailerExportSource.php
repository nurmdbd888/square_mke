<?php

namespace App\Domain\Export\Sources;

use App\Domain\Export\ExportSource;
use App\Models\Retailer;
use Illuminate\Support\LazyCollection;

final class RetailerExportSource implements ExportSource
{
    public function allColumns(): array
    {
        return ['code', 'name', 'phone', 'address', 'credit_limit'];
    }

    public function defaultColumns(): array
    {
        return $this->allColumns();
    }

    public function rows(array $filters, array $columns): array
    {
        $columns = array_values(array_intersect($this->allColumns(), $columns));
        $status = $filters['status'] ?? 'active';

        $query = Retailer::query()
            ->when($status === 'inactive', fn ($q) => $q->onlyTrashed())
            ->when($status === 'all', fn ($q) => $q->withTrashed())
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes((string) $term, '\\%_').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like));
            })
            ->orderBy('name');

        $headers = array_map(fn ($c) => str_replace('_', ' ', ucfirst($c)), $columns);

        $rows = LazyCollection::make(function () use ($query, $columns) {
            foreach ($query->cursor() as $retailer) {
                $line = [];
                foreach ($columns as $col) {
                    $line[] = match ($col) {
                        'code' => $retailer->code,
                        'name' => $retailer->name,
                        'phone' => $retailer->phone,
                        'address' => $retailer->address,
                        'credit_limit' => $retailer->credit_limit,
                        default => null,
                    };
                }
                yield $line;
            }
        });

        return ['headers' => $headers, 'rows' => $rows];
    }
}
