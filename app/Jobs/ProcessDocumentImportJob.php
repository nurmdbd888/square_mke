<?php

namespace App\Jobs;

use App\Domain\Import\DocumentImportService;
use App\Domain\Import\InvoiceSpreadsheetReader;
use App\Models\DocumentImportBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ProcessDocumentImportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $batchId) {}

    public function handle(InvoiceSpreadsheetReader $reader, DocumentImportService $imports): void
    {
        $batch = DocumentImportBatch::query()->find($this->batchId);
        if ($batch === null || $batch->stored_path === null) {
            return;
        }

        try {
            $path = Storage::path($batch->stored_path);
            $profile = $batch->mappingProfile?->mapping;
            $meta = $batch->meta ?? [];
            $parsed = $reader->read($path, $batch->doc_type, $profile, $imports->defaultsFromMeta($batch->doc_type, $meta));
            $batch->update(['layout' => $parsed['layout']]);
            $imports->persistParsed($batch, $parsed['invoices']);
        } catch (\Throwable $e) {
            $batch->update(['status' => DocumentImportBatch::STATUS_FAILED]);
            throw $e;
        }
    }
}
