<?php

use App\Jobs\RefreshGoogleSheetJob;
use App\Models\GoogleSheetSubscription;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Scheduled work runs in-process (Schedule::call), never as a child process
// (Schedule::command / exec): shared hosts often disable proc_open and exec.
Schedule::call(fn () => Artisan::call('sdm:heartbeat'))
    ->name('sdm:heartbeat')
    ->everyMinute();

// Drain the database queue in-process (shared hosts often disable proc_open).
Schedule::call(fn () => Artisan::call('queue:work', [
    'connection' => 'database',
    '--stop-when-empty' => true,
    '--max-time' => 50,
    '--memory' => 96,
]))
    ->name('queue:work')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::call(function () {
    GoogleSheetSubscription::query()
        ->whereNotNull('next_refresh_at')
        ->where('next_refresh_at', '<=', now())
        ->orderBy('id')
        ->limit(10)
        ->pluck('id')
        ->each(fn (int $id) => RefreshGoogleSheetJob::dispatch($id));
})->name('google:refresh-sheets')->everyMinute()->withoutOverlapping(5);
