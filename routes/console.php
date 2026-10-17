<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Scheduled work runs in-process (Schedule::call), never as a child process
// (Schedule::command / exec): shared hosts often disable proc_open and exec.
Schedule::call(fn () => Artisan::call('sdm:heartbeat'))
    ->name('sdm:heartbeat')
    ->everyMinute();
