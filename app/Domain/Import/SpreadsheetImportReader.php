<?php

namespace App\Domain\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class SpreadsheetImportReader
{
    /**
     * @return list<array{row_no: int, raw: array<string, string|null>, parsed: array<string, mixed>, errors: list<string>}>
     */
    public function read(string $entity, string $path, string $originalName, ?array $profileMapping = null): array
    {
        $def = EntityImportRegistry::get($entity);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($ext, ['csv', 'xls', 'xlsx'], true)) {
            return [[
                'row_no' => 1,
                'raw' => [],
                'parsed' => [],
                'errors' => [__('This file type is not supported. Use .csv, .xls, or .xlsx.')],
            ]];
        }

        $sheet = IOFactory::load($path)->getActiveSheet();
        [$headerRow, $dataStart] = $this->detectLayout($entity, $originalName, $sheet);

        $headers = $this->rowValues($sheet, $headerRow);
        $fieldMap = $this->buildFieldMap($def, $headers, $profileMapping, $entity, $originalName);

        if ($fieldMap === []) {
            return [[
                'row_no' => 1,
                'raw' => $headers,
                'parsed' => [],
                'errors' => [__('Could not match column headers. Download the template or save a mapping profile.')],
            ]];
        }

        $rows = [];
        $rowNo = 0;
        $highest = (int) $sheet->getHighestRow();

        for ($r = $dataStart; $r <= $highest; $r++) {
            $cells = $this->rowValues($sheet, $r);
            if ($this->rowEmpty($cells)) {
                continue;
            }
            $parsed = [];
            $raw = [];
            foreach ($fieldMap as $colIndex => $fieldKey) {
                $val = trim((string) ($cells[$colIndex] ?? ''));
                $raw[$fieldKey] = $val === '' ? null : $val;
                $parsed[$fieldKey] = $val === '' ? null : $val;
            }
            $errors = $this->validateRow($def, $parsed);
            $rowNo++;
            $rows[] = [
                'row_no' => $rowNo,
                'raw' => $raw,
                'parsed' => $parsed,
                'errors' => $errors,
            ];
        }

        return $rows;
    }

    /**
     * @return array{0: int, 1: int} header row, first data row
     */
    private function detectLayout(string $entity, string $originalName, Worksheet $sheet): array
    {
        if ($entity === 'products' && preg_match('/^ProductList/i', basename($originalName))) {
            return [2, 4];
        }
        if ($entity === 'retailers' && preg_match('/^SalesRegister/i', basename($originalName))) {
            return [1, 4];
        }

        return [1, 2];
    }

    /**
     * @param  array<int, string|null>  $headers
     * @return array<int, string> col index => field key
     */
    private function buildFieldMap(array $def, array $headers, ?array $profileMapping, string $entity, string $originalName): array
    {
        if ($profileMapping !== null && $profileMapping !== []) {
            $map = [];
            foreach ($profileMapping as $colLabel => $fieldKey) {
                $idx = $this->findHeaderIndex($headers, (string) $colLabel);
                if ($idx !== null && $fieldKey !== '') {
                    $map[$idx] = (string) $fieldKey;
                }
            }

            return $map;
        }

        $aliases = $this->defaultAliases($entity, $originalName);
        $map = [];
        foreach ($def['columns'] as $col) {
            $key = $col['key'];
            $labels = array_merge([$col['label']], $aliases[$key] ?? []);
            foreach ($labels as $label) {
                $idx = $this->findHeaderIndex($headers, $label);
                if ($idx !== null) {
                    $map[$idx] = $key;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * @return array<string, list<string>>
     */
    private function defaultAliases(string $entity, string $originalName): array
    {
        if ($entity === 'products' && preg_match('/^ProductList/i', basename($originalName))) {
            return [
                'code' => ['Product Code', 'product code'],
                'name_en' => ['Product Name', 'product name'],
                'pack_size' => ['Pack Size'],
                'buy_price' => ['TP', ' TP', 'Buy price'],
            ];
        }
        if ($entity === 'retailers' && preg_match('/^SalesRegister/i', basename($originalName))) {
            return [
                'code' => ['Retailer Code'],
                'name' => ['Retailer Name', 'Name'],
            ];
        }

        return [];
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

    /**
     * @param  array<int, string|null>  $cells
     */
    private function rowEmpty(array $cells): bool
    {
        foreach ($cells as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return list<string>
     */
    private function validateRow(array $def, array $parsed): array
    {
        $errors = [];
        foreach ($def['columns'] as $col) {
            if (! empty($col['required']) && empty($parsed[$col['key']])) {
                $errors[] = __(':field is required.', ['field' => __($col['label'])]);
            }
        }

        return $errors;
    }
}
