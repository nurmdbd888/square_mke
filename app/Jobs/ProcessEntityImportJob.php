<?php

namespace App\Jobs;

use App\Domain\Import\EntityImportService;
use App\Domain\Import\SpreadsheetImportReader;
use App\Models\EntityImportBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ProcessEntityImportJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string>|null  $profileMapping
     */
    public function __construct(
        public int $batchId,
        public string $storedPath,
        public string $entity,
        public string $originalName,
        public ?array $profileMapping = null,
    ) {}

    public function handle(SpreadsheetImportReader $reader, EntityImportService $imports): void
    {
        $batch = EntityImportBatch::query()->find($this->batchId);
        if ($batch === null) {
            return;
        }

        $path = Storage::path($this->storedPath);
        $parsed = $reader->read($this->entity, $path, $this->originalName, $this->profileMapping);
        $imports->persistRows($batch, $parsed);
    }
}
