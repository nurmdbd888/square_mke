<?php

namespace App\Domain\Export;

interface ExportSource
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $columns
     * @return array{headers: list<string>, rows: iterable<int, list<string|null>>}
     */
    public function rows(array $filters, array $columns): array;

    /**
     * @return list<string>
     */
    public function defaultColumns(): array;

    /**
     * @return list<string>
     */
    public function allColumns(): array;
}
