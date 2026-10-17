<?php

namespace App\Domain\Export;

use App\Jobs\RunTableExportJob;
use App\Models\AppJob;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\User;
use Illuminate\Support\Str;

final class ExportService
{
    public function __construct(
        private readonly ExportWriter $writer,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $columns
     */
    public function start(string $key, string $format, array $filters, array $columns, User $user): AppJob
    {
        ExportRegistry::get($key);
        $def = ExportRegistry::get($key);
        $columns = $columns === [] ? ExportRegistry::source($key)->defaultColumns() : $columns;

        $source = ExportRegistry::source($key);
        $estimated = $this->estimateRows($key, $filters);
        $inline = $estimated <= (int) config('sdm.export_inline_limit', 500);

        $job = AppJob::query()->create([
            'user_id' => $user->id,
            'type' => 'table_export',
            'status' => AppJob::STATUS_PENDING,
            'label' => __($def['title']).' ('.strtoupper($format).')',
            'payload' => [
                'key' => $key,
                'format' => $format,
                'filters' => $filters,
                'columns' => $columns,
            ],
        ]);

        if ($inline) {
            $this->run($job);

            return $job->fresh();
        }

        RunTableExportJob::dispatch($job->id);

        return $job;
    }

    public function run(AppJob $job): void
    {
        $payload = $job->payload ?? [];
        $key = (string) ($payload['key'] ?? '');
        $format = (string) ($payload['format'] ?? 'csv');
        $filters = (array) ($payload['filters'] ?? []);
        $columns = (array) ($payload['columns'] ?? []);

        $job->markRunning();
        try {
            $source = ExportRegistry::source($key);
            $data = $source->rows($filters, $columns);
            $dir = storage_path('app/exports');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $filename = Str::slug($key).'-'.now()->format('Ymd-His').'.'.$format;
            $path = $dir.'/'.$filename;
            $mime = $this->writer->write($format, $path, $data['headers'], $data['rows']);
            $job->markDone('exports/'.$filename, $mime);
        } catch (\Throwable $e) {
            $job->markFailed($e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function estimateRows(string $key, array $filters): int
    {
        return match ($key) {
            'products' => Product::query()->count(),
            'retailers' => Retailer::query()->count(),
            'reports_pl' => 9,
            default => 100,
        };
    }
}
