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
            if (isset($meta['ocr_payload_path'])) {
                $payloadPath = (string) $meta['ocr_payload_path'];
                $invoices = json_decode(Storage::get($payloadPath), true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($invoices)) {
                    throw new \UnexpectedValueException('The parsed OCR payload is invalid.');
                }
                $imports->persistParsed($batch, $invoices);
                Storage::delete($payloadPath);
            } else {
                $parsed = $reader->read($path, $batch->doc_type, $profile, $imports->defaultsFromMeta($batch->doc_type, $meta));
                $batch->update(['layout' => $parsed['layout']]);
                $imports->persistParsed($batch, $parsed['invoices']);
            }
        } catch (\Throwable $e) {
            $batch->update(['status' => DocumentImportBatch::STATUS_FAILED]);
            throw $e;
        }
    }
}
