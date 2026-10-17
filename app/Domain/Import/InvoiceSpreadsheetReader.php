<?php

namespace App\Domain\Import;

use App\Models\DocumentImportBatch;
use App\Models\DocumentImportLine;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class InvoiceSpreadsheetReader
{
    /**
     * @param  array<string, mixed>  $defaults
     * @return array{layout: string, invoices: list<array{invoice_no: string, header: array<string, mixed>, lines: list<array<string, mixed>>}>}
     */
    public function read(string $path, string $docType, ?array $profileMapping = null, array $defaults = []): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($ext, ['csv', 'xls', 'xlsx'], true)) {
            throw new \InvalidArgumentException(__('This file type is not supported. Use .csv, .xls, or .xlsx.'));
        }

        $book = IOFactory::load($path);
        $sheetCount = $book->getSheetCount();

        if ($sheetCount >= 2) {
            $headerSheet = $book->getSheet(0);
            $lineSheet = $book->getSheet(1);
            $headerMap = $this->mapHeaders($headerSheet, 1, InvoiceImportTemplates::headerColumns($docType), $docType, true);
            $lineMap = $this->mapHeaders($lineSheet, 1, InvoiceImportTemplates::lineColumns($docType), $docType, false, $profileMapping);

            return [
                'layout' => 'header_lines',
                'invoices' => $this->mergeHeaderLines($docType, $headerSheet, $lineSheet, $headerMap, $lineMap, $defaults),
            ];
        }

        $sheet = $book->getActiveSheet();
        $flatHeaders = InvoiceImportTemplates::flatColumns($docType);
        $firstRow = $this->rowValues($sheet, 1);
        $hasDocType = $this->rowHas($firstRow, ['doc_type', 'Doc Type']);

        if ($hasDocType) {
            $map = $this->mapHeaders($sheet, 1, $flatHeaders, $docType, false, $profileMapping);

            return [
                'layout' => 'flat',
                'invoices' => $this->readFlatRows($docType, $sheet, $map, 2),
            ];
        }

        $lineCols = InvoiceImportTemplates::lineColumns($docType);
        $map = $this->mapHeaders($sheet, 1, $lineCols, $docType, false, $profileMapping);

        return [
            'layout' => 'lines_only',
            'invoices' => $this->readLinesOnly($docType, $sheet, $map, 2, $defaults),
        ];
    }

    /**
     * @param  list<string>  $fields
     * @return array<int, string>
     */
    private function mapHeaders(
        Worksheet $sheet,
        int $headerRow,
        array $fields,
        string $docType,
        bool $isHeaderSheet,
        ?array $profileMapping = null,
    ): array {
        $headers = $this->rowValues($sheet, $headerRow);
        $aliases = InvoiceImportTemplates::lineHeaderAliases($docType);
        $map = [];

        if ($profileMapping !== null && $profileMapping !== []) {
            foreach ($profileMapping as $colLabel => $fieldKey) {
                $idx = $this->findHeaderIndex($headers, (string) $colLabel);
                if ($idx !== null && $fieldKey !== '') {
                    $map[$idx] = (string) $fieldKey;
                }
            }

            return $map;
        }

        foreach ($fields as $field) {
            $labels = array_merge([$field, str_replace('_', ' ', $field)], $aliases[$field] ?? []);
            foreach ($labels as $label) {
                $idx = $this->findHeaderIndex($headers, $label);
                if ($idx !== null) {
                    $map[$idx] = $field;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $headerMap
     * @param  array<int, string>  $lineMap
     * @param  array<string, mixed>  $defaults
     * @return list<array{invoice_no: string, header: array<string, mixed>, lines: list<array<string, mixed>>}>
     */
    private function mergeHeaderLines(
        string $docType,
        Worksheet $headerSheet,
        Worksheet $lineSheet,
        array $headerMap,
        array $lineMap,
        array $defaults,
    ): array {
        $headersByInvoice = [];
        for ($r = 2; $r <= (int) $headerSheet->getHighestRow(); $r++) {
            $row = $this->mappedRow($headerSheet, $r, $headerMap);
            if ($this->rowBlank($row)) {
                continue;
            }
            $no = (string) ($row['invoice_no'] ?? $defaults['invoice_no'] ?? '');
            if ($no === '') {
                continue;
            }
            $headersByInvoice[$no] = $row;
        }

        $grouped = [];
        for ($r = 2; $r <= (int) $lineSheet->getHighestRow(); $r++) {
            $row = $this->mappedRow($lineSheet, $r, $lineMap);
            if ($this->shouldSkipLineRow($docType, $row)) {
                continue;
            }
            $no = (string) ($row['invoice_no'] ?? '');
            if ($no === '') {
                $no = count($headersByInvoice) === 1
                    ? (string) array_key_first($headersByInvoice)
                    : (string) ($defaults['invoice_no'] ?? '');
            }
            if ($no === '') {
                continue;
            }
            $grouped[$no][] = $this->normalizeLineFields($docType, $row);
        }

        $invoices = [];
        foreach ($grouped as $no => $lines) {
            $header = $headersByInvoice[$no] ?? ['invoice_no' => $no];
            $header = array_merge($defaults, $header);
            $invoices[] = ['invoice_no' => $no, 'header' => $header, 'lines' => $lines];
        }

        return $invoices;
    }

    /**
     * @param  array<int, string>  $map
     * @return list<array{invoice_no: string, header: array<string, mixed>, lines: list<array<string, mixed>>}>
     */
    private function readFlatRows(string $docType, Worksheet $sheet, array $map, int $startRow): array
    {
        $grouped = [];
        for ($r = $startRow; $r <= (int) $sheet->getHighestRow(); $r++) {
            $row = $this->mappedRow($sheet, $r, $map);
            if ($this->shouldSkipLineRow($docType, $row)) {
                continue;
            }
            $no = (string) ($row['invoice_no'] ?? '');
            if ($no === '') {
                continue;
            }
            $header = $row;
            $line = $this->normalizeLineFields($docType, $row);
            $grouped[$no]['header'] ??= $header;
            $grouped[$no]['lines'][] = $line;
        }

        $out = [];
        foreach ($grouped as $no => $payload) {
            $out[] = ['invoice_no' => $no, 'header' => $payload['header'], 'lines' => $payload['lines']];
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $map
     * @param  array<string, mixed>  $defaults
     * @return list<array{invoice_no: string, header: array<string, mixed>, lines: list<array<string, mixed>>}>
     */
    private function readLinesOnly(string $docType, Worksheet $sheet, array $map, int $startRow, array $defaults): array
    {
        $lines = [];
        for ($r = $startRow; $r <= (int) $sheet->getHighestRow(); $r++) {
            $row = $this->mappedRow($sheet, $r, $map);
            if ($this->shouldSkipLineRow($docType, $row)) {
                continue;
            }
            $lines[] = $this->normalizeLineFields($docType, $row);
        }
        $no = (string) ($defaults['invoice_no'] ?? 'IMPORT-'.now()->format('YmdHis'));

        return [[
            'invoice_no' => $no,
            'header' => $defaults,
            'lines' => $lines,
        ]];
    }

    /**
     * @param  array<int, string>  $map
     * @return array<string, mixed>
     */
    private function mappedRow(Worksheet $sheet, int $row, array $map): array
    {
        $cells = $this->rowValues($sheet, $row);
        $out = [];
        foreach ($map as $idx => $field) {
            $out[$field] = trim((string) ($cells[$idx] ?? ''));
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function shouldSkipLineRow(string $docType, array $row): bool
    {
        if ($this->rowBlank($row)) {
            return true;
        }
        $joined = mb_strtolower(implode(' ', array_map(strval(...), $row)));
        if (str_contains($joined, 'sub total') && trim((string) ($row['serial'] ?? $row['product_name'] ?? '')) === '') {
            return true;
        }
        $label = trim((string) ($row['product_description'] ?? $row['product_name'] ?? ''));
        $qty = InvoiceText::parseNumber($row['qty'] ?? null) ?? '0';
        if ($label === '' && trim((string) ($row['serial'] ?? '')) === '' && ($qty === '0' || $qty === '0.0')) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeLineFields(string $docType, array $row): array
    {
        if ($docType === DocumentImportBatch::TYPE_PURCHASE) {
            return [
                'serial' => $row['serial'] ?? null,
                'product_code' => $row['product_code'] ?? null,
                'product_label' => $row['product_description'] ?? $row['product_name'] ?? '',
                'pack_size' => null,
                'unit' => $row['unit'] ?? null,
                'qty' => InvoiceText::parseNumber($row['qty'] ?? null) ?? '0',
                'bonus_qty' => InvoiceText::parseNumber($row['bonus_qty'] ?? null) ?? '0',
                'unit_price' => InvoiceText::parseNumber($row['unit_price_ex_tax'] ?? $row['unit_price'] ?? null),
                'line_discount' => InvoiceText::parseNumber($row['line_discount'] ?? null) ?? '0',
                'line_value' => InvoiceText::parseNumber($row['value_ex_tax'] ?? $row['value'] ?? null),
                'sd_pct' => InvoiceText::parsePercent($row['sd_pct'] ?? null) ?? '0',
                'sd_amount' => InvoiceText::parseNumber($row['sd_amount'] ?? null) ?? '0',
                'vat_pct' => InvoiceText::parsePercent($row['vat_pct'] ?? null) ?? '15',
                'vat_amount' => InvoiceText::parseNumber($row['vat_amount'] ?? null) ?? '0',
                'total_incl_tax' => InvoiceText::parseNumber($row['total_incl_tax'] ?? $row['total_incl_vat'] ?? null),
                'line_type' => DocumentImportLine::TYPE_INVOICE,
                'section' => null,
            ];
        }

        $amountRaw = (string) ($row['amount'] ?? '');
        $lineType = strtolower(trim((string) ($row['line_type'] ?? 'invoice')));
        if (strcasecmp(trim($amountRaw), 'Bonus') === 0) {
            $lineType = 'bonus';
        }
        $section = trim((string) ($row['section'] ?? ''));
        if ($section !== '' && str_contains(mb_strtolower($section), 'combo')) {
            $lineType = 'combo_bonus';
        }

        return [
            'serial' => $row['serial'] ?? null,
            'product_code' => $row['product_code'] ?? null,
            'product_label' => $row['product_name'] ?? $row['product_description'] ?? '',
            'pack_size' => $row['pack_size'] ?? null,
            'qty' => InvoiceText::parseNumber($row['qty'] ?? null) ?? '0',
            'bonus_qty' => InvoiceText::parseNumber($row['bonus_qty'] ?? null) ?? '0',
            'unit_price' => InvoiceText::parseNumber($row['unit_price'] ?? null),
            'line_discount' => InvoiceText::parseNumber($row['prod_discount'] ?? $row['line_discount'] ?? null) ?? '0',
            'line_value' => InvoiceText::parseNumber($row['amount'] ?? null) ?? '0',
            'sd_pct' => '0',
            'sd_amount' => '0',
            'vat_pct' => '0',
            'vat_amount' => '0',
            'total_incl_tax' => InvoiceText::parseNumber($row['amount'] ?? null),
            'line_type' => $lineType,
            'section' => $section !== '' ? $section : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowBlank(array $row): bool
    {
        foreach ($row as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string|null>  $headers
     * @param  list<string>  $needles
     */
    private function rowHas(array $headers, array $needles): bool
    {
        foreach ($headers as $h) {
            foreach ($needles as $n) {
                if ($h !== null && mb_strtolower(trim((string) $h)) === mb_strtolower($n)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, string|null>  $headers
     */
    private function findHeaderIndex(array $headers, string $label): ?int
    {
        $needle = mb_strtolower(trim($label));
        foreach ($headers as $i => $h) {
            if ($h !== null && mb_strtolower(trim((string) $h)) === $needle) {
                return (int) $i;
            }
        }

        return null;
    }

    /**
     * @return array<int, string|null>
     */
    private function rowValues(Worksheet $sheet, int $row): array
    {
        $out = [];
        $max = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        for ($c = 1; $c <= $max; $c++) {
            $out[$c] = $sheet->getCell([$c, $row])->getFormattedValue();
        }

        return $out;
    }
}
