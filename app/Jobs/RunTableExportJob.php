<?php

namespace App\Jobs;

use App\Domain\Export\ExportService;
use App\Models\AppJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunTableExportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $appJobId) {}

    public function handle(ExportService $exports): void
    {
        $job = AppJob::query()->find($this->appJobId);
        if ($job === null || $job->status !== AppJob::STATUS_PENDING) {
            return;
        }
        $exports->run($job);
    }
}
