<?php

namespace App\Domain\Import;

use App\Domain\Money\Exact;
use App\Jobs\ProcessEntityImportJob;
use App\Models\EntityImportBatch;
use App\Models\EntityImportRow;
use App\Models\ImportMappingProfile;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\SalesRep;
use App\Models\SalesRoute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EntityImportService
{
    public function __construct(private readonly SpreadsheetImportReader $reader) {}

    public function templateDownload(string $entity, string $format = 'csv'): StreamedResponse
    {
        EntityImportRegistry::get($entity);
        $headers = EntityImportRegistry::templateHeaders($entity);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($headers as $i => $label) {
            $sheet->setCellValue([$i + 1, 1], $label);
        }

        $filename = "{$entity}-import-template.{$format}";

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'xlsx' ? new XlsxWriter($spreadsheet) : new CsvWriter($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => $format === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv',
        ]);
    }

    /**
     * @return array{batch: EntityImportBatch, queued: bool}
     */
    public function preview(string $entity, UploadedFile $file, ?User $by = null, ?int $profileId = null): array
    {
        $def = EntityImportRegistry::get($entity);
        $stored = $file->store('entity-imports');
        $path = Storage::path($stored);
        $profile = $profileId ? ImportMappingProfile::query()->find($profileId)?->mapping : null;

        $parsed = $this->reader->read($entity, $path, $file->getClientOriginalName(), $profile);
        $inline = (int) config('sdm.entity_import_inline_limit', 500);

        $batch = EntityImportBatch::query()->create([
            'entity' => $entity,
            'original_name' => $file->getClientOriginalName(),
            'status' => EntityImportBatch::STATUS_PREVIEW,
            'row_count' => count($parsed),
            'error_count' => 0,
            'mapping_profile_id' => $profileId,
            'created_by' => $by?->id,
        ]);

        if (count($parsed) > $inline) {
            ProcessEntityImportJob::dispatch($batch->id, $stored, $entity, $file->getClientOriginalName(), $profile);

            return ['batch' => $batch, 'queued' => true];
        }

        $this->persistRows($batch, $parsed);

        return ['batch' => $batch->fresh('rows'), 'queued' => false];
    }

    /**
     * @param  list<array{row_no: int, raw: array<mixed>, parsed: array<string, mixed>, errors: list<string>}>  $parsed
     */
    public function persistRows(EntityImportBatch $batch, array $parsed): void
    {
        $errors = 0;
        foreach ($parsed as $row) {
            $rowErrors = $row['errors'];
            if ($rowErrors !== []) {
                $errors++;
            }
            EntityImportRow::query()->create([
                'entity_import_batch_id' => $batch->id,
                'row_no' => $row['row_no'],
                'raw' => $row['raw'],
                'parsed' => $row['parsed'],
                'status' => $rowErrors === [] ? EntityImportRow::STATUS_OK : EntityImportRow::STATUS_ERROR,
                'errors' => $rowErrors === [] ? null : implode('; ', $rowErrors),
            ]);
        }
        $batch->update(['row_count' => count($parsed), 'error_count' => $errors]);
    }

    public function confirm(EntityImportBatch $batch, string $duplicateMode, ?User $by = null): EntityImportBatch
    {
        if ($batch->status !== EntityImportBatch::STATUS_PREVIEW) {
            throw ValidationException::withMessages(['status' => __('This batch was already posted or undone.')]);
        }
        if (! in_array($duplicateMode, [EntityImportBatch::MODE_UPDATE, EntityImportBatch::MODE_SKIP], true)) {
            throw ValidationException::withMessages(['duplicate_mode' => __('Choose update or skip for duplicates.')]);
        }
        if ((int) $batch->error_count > 0) {
            throw ValidationException::withMessages([
                'import' => __('Fix or remove :count error rows, then upload again. Nothing is imported while errors remain.', [
                    'count' => (string) $batch->error_count,
                ]),
            ]);
        }

        $def = EntityImportRegistry::get($batch->entity);

        return DB::transaction(function () use ($batch, $duplicateMode, $def) {
            $batch->update(['duplicate_mode' => $duplicateMode]);
            $batch->load('rows');
            $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'restored' => 0, 'undo' => []];

            foreach ($batch->rows as $row) {
                if ($row->hasErrors()) {
                    continue;
                }
                $result = $this->applyRow($def, $batch->entity, $row->parsed ?? [], $duplicateMode);
                if ($result === null) {
                    $row->update(['status' => EntityImportRow::STATUS_SKIPPED, 'errors' => __('Duplicate skipped.')]);
                    $summary['skipped']++;

                    continue;
                }
                $summary['undo'][] = $result['undo'];
                if ($result['created']) {
                    $summary['created']++;
                } else {
                    $summary['updated']++;
                }
                if ($result['restored']) {
                    $summary['restored']++;
                }
            }

            $batch->update([
                'status' => EntityImportBatch::STATUS_POSTED,
                'posted_at' => now(),
                'summary' => $summary,
            ]);

            return $batch->fresh();
        });
    }

    public function undo(EntityImportBatch $batch, ?User $by = null): EntityImportBatch
    {
        if ($batch->status !== EntityImportBatch::STATUS_POSTED) {
            throw ValidationException::withMessages(['status' => __('Only a posted batch can be undone.')]);
        }

        return DB::transaction(function () use ($batch) {
            $undo = $batch->summary['undo'] ?? [];
            foreach (array_reverse($undo) as $entry) {
                $this->revertUndoEntry($entry);
            }
            $batch->update([
                'status' => EntityImportBatch::STATUS_UNDONE,
                'undone_at' => now(),
            ]);

            return $batch->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{created: bool, restored: bool, undo: array<string, mixed>}|null
     */
    private function applyRow(array $def, string $entity, array $parsed, string $duplicateMode): ?array
    {
        return match ($entity) {
            'products' => $this->applyProduct($parsed, $duplicateMode),
            'retailers' => $this->applyRetailer($parsed, $duplicateMode),
            'price_list_items' => $this->applyPriceListItem($parsed, $duplicateMode),
            default => $this->applySimple($def, $parsed, $duplicateMode),
        };
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{created: bool, restored: bool, undo: array<string, mixed>}|null
     */
    private function applySimple(array $def, array $parsed, string $duplicateMode): ?array
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $def['model'];
        $unique = $def['unique'];
        $key = (string) ($parsed[$unique] ?? '');
        if ($key === '') {
            return null;
        }

        $existing = $modelClass::query()->withTrashed()->where($unique, $key)->first();
        if ($existing !== null && ! $existing->trashed() && $duplicateMode === EntityImportBatch::MODE_SKIP) {
            return null;
        }

        $attrs = $this->filterAttributes($def, $parsed);
        if ($existing === null) {
            $created = $modelClass::query()->create($attrs);

            return [
                'created' => true,
                'restored' => false,
                'undo' => ['action' => 'delete', 'model' => $modelClass, 'id' => $created->id],
            ];
        }

        $before = $existing->only(array_keys($attrs));
        $restored = $existing->trashed();
        if ($restored) {
            $existing->restore();
        }
        $existing->update($attrs);

        return [
            'created' => false,
            'restored' => $restored,
            'undo' => ['action' => 'restore_attrs', 'model' => $modelClass, 'id' => $existing->id, 'before' => $before, 'was_trashed' => $restored],
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{created: bool, restored: bool, undo: array<string, mixed>}|null
     */
    private function applyProduct(array $parsed, string $duplicateMode): ?array
    {
        $code = (string) ($parsed['code'] ?? '');
        if ($code === '') {
            return null;
        }
        $existing = Product::query()->withTrashed()->where('code', $code)->first();
        if ($existing !== null && ! $existing->trashed() && $duplicateMode === EntityImportBatch::MODE_SKIP) {
            return null;
        }

        $attrs = array_filter([
            'code' => $code,
            'name_en' => $parsed['name_en'] ?? $code,
            'name_bn' => $parsed['name_bn'] ?? null,
            'brand' => $parsed['brand'] ?? null,
            'pack_size' => $parsed['pack_size'] ?? null,
            'unit' => $parsed['unit'] ?? 'Pcs',
            'buy_price' => isset($parsed['buy_price']) ? Exact::money(Exact::of($parsed['buy_price'])) : null,
            'sell_price' => isset($parsed['sell_price']) ? Exact::money(Exact::of($parsed['sell_price'])) : null,
            'sd_pct' => $parsed['sd_pct'] ?? null,
            'vat_pct' => $parsed['vat_pct'] ?? null,
        ], fn ($v) => $v !== null);

        if ($existing === null) {
            $created = Product::query()->create($attrs);

            return ['created' => true, 'restored' => false, 'undo' => ['action' => 'delete', 'model' => Product::class, 'id' => $created->id]];
        }

        $before = $existing->only(array_keys($attrs));
        $restored = $existing->trashed();
        if ($restored) {
            $existing->restore();
        }
        $existing->update($attrs);

        return [
            'created' => false,
            'restored' => $restored,
            'undo' => ['action' => 'restore_attrs', 'model' => Product::class, 'id' => $existing->id, 'before' => $before, 'was_trashed' => $restored],
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{created: bool, restored: bool, undo: array<string, mixed>}|null
     */
    private function applyRetailer(array $parsed, string $duplicateMode): ?array
    {
        $code = (string) ($parsed['code'] ?? '');
        if ($code === '') {
            return null;
        }
        $existing = Retailer::query()->withTrashed()->where('code', $code)->first();
        if ($existing !== null && ! $existing->trashed() && $duplicateMode === EntityImportBatch::MODE_SKIP) {
            return null;
        }

        $attrs = [
            'code' => $code,
            'name' => (string) ($parsed['name'] ?? $code),
            'address' => $parsed['address'] ?? null,
            'phone' => $parsed['phone'] ?? null,
            'credit_limit' => isset($parsed['credit_limit']) ? Exact::money(Exact::of($parsed['credit_limit'])) : null,
        ];
        if (! empty($parsed['route_code'])) {
            $attrs['route_id'] = SalesRoute::query()->where('code', $parsed['route_code'])->value('id');
        }
        if (! empty($parsed['sales_rep_code'])) {
            $attrs['sales_rep_id'] = SalesRep::query()->where('code', $parsed['sales_rep_code'])->value('id');
        }

        if ($existing === null) {
            $created = Retailer::query()->create($attrs);

            return ['created' => true, 'restored' => false, 'undo' => ['action' => 'delete', 'model' => Retailer::class, 'id' => $created->id]];
        }

        $before = $existing->only(array_keys($attrs));
        $restored = $existing->trashed();
        if ($restored) {
            $existing->restore();
        }
        $existing->update($attrs);

        return [
            'created' => false,
            'restored' => $restored,
            'undo' => ['action' => 'restore_attrs', 'model' => Retailer::class, 'id' => $existing->id, 'before' => $before, 'was_trashed' => $restored],
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{created: bool, restored: bool, undo: array<string, mixed>}|null
     */
    private function applyPriceListItem(array $parsed, string $duplicateMode): ?array
    {
        $listName = (string) ($parsed['price_list_name'] ?? '');
        $productCode = (string) ($parsed['product_code'] ?? '');
        if ($listName === '' || $productCode === '') {
            return null;
        }
        $listId = PriceList::query()->where('name', $listName)->value('id');
        $productId = Product::query()->where('code', $productCode)->value('id');
        if ($listId === null || $productId === null) {
            return null;
        }

        $existing = PriceListItem::query()
            ->where('price_list_id', $listId)
            ->where('product_id', $productId)
            ->first();

        if ($existing !== null && $duplicateMode === EntityImportBatch::MODE_SKIP) {
            return null;
        }

        $attrs = [
            'price_list_id' => $listId,
            'product_id' => $productId,
            'price' => Exact::money(Exact::of($parsed['price'] ?? '0')),
            'effective_from' => $parsed['effective_from'] ?? now()->toDateString(),
        ];

        if ($existing === null) {
            $created = PriceListItem::query()->create($attrs);

            return ['created' => true, 'restored' => false, 'undo' => ['action' => 'delete', 'model' => PriceListItem::class, 'id' => $created->id]];
        }

        $before = $existing->only(['price', 'effective_from']);
        $existing->update($attrs);

        return [
            'created' => false,
            'restored' => false,
            'undo' => ['action' => 'restore_attrs', 'model' => PriceListItem::class, 'id' => $existing->id, 'before' => $before, 'was_trashed' => false],
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function filterAttributes(array $def, array $parsed): array
    {
        $keys = array_column($def['columns'], 'key');
        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $parsed) && $parsed[$key] !== null && $parsed[$key] !== '') {
                $out[$key] = $parsed[$key];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function revertUndoEntry(array $entry): void
    {
        /** @var class-string<Model> $model */
        $model = $entry['model'];
        $record = $model::query()->withTrashed()->find($entry['id']);
        if ($record === null) {
            return;
        }

        if ($entry['action'] === 'delete') {
            $record->forceDelete();

            return;
        }

        if ($entry['action'] === 'restore_attrs') {
            $record->update($entry['before'] ?? []);
            if ($entry['was_trashed'] ?? false) {
                $record->delete();
            }
        }
    }

    public function exportList(string $entity): StreamedResponse
    {
        $def = EntityImportRegistry::get($entity);
        /** @var class-string<Model> $modelClass */
        $modelClass = $def['model'];
        $headers = EntityImportRegistry::templateHeaders($entity);
        $keys = array_column($def['columns'], 'key');

        return response()->streamDownload(function () use ($modelClass, $headers, $keys, $entity) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            $modelClass::query()->orderBy('id')->chunk(200, function ($chunk) use ($out, $keys, $entity) {
                foreach ($chunk as $row) {
                    $line = [];
                    foreach ($keys as $key) {
                        $line[] = $this->exportCell($entity, $row, $key);
                    }
                    fputcsv($out, $line);
                }
            });
            fclose($out);
        }, "{$entity}-export.csv", ['Content-Type' => 'text/csv']);
    }

    private function exportCell(string $entity, Model $row, string $key): string
    {
        if ($entity === 'retailers' && $key === 'route_code') {
            return (string) (SalesRoute::query()->find($row->route_id)?->code ?? '');
        }
        if ($entity === 'retailers' && $key === 'sales_rep_code') {
            return (string) (SalesRep::query()->find($row->sales_rep_id)?->code ?? '');
        }
        if ($entity === 'price_list_items' && $key === 'price_list_name') {
            return '';
        }

        return (string) ($row->getAttribute($key) ?? '');
    }
}
