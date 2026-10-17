<?php

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
