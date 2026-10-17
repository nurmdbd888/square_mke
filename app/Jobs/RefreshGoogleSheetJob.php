<?php

namespace App\Jobs;

use App\Domain\Google\GoogleSheetsService;
use App\Models\AppJob;
use App\Models\GoogleSheetSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshGoogleSheetJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $subscriptionId) {}

    public function handle(GoogleSheetsService $sheets): void
    {
        $sub = GoogleSheetSubscription::query()->find($this->subscriptionId);
        if ($sub === null) {
            return;
        }

        $job = AppJob::query()->create([
            'user_id' => $sub->created_by,
            'type' => 'google_sheet_refresh',
            'status' => AppJob::STATUS_RUNNING,
            'label' => __('Refresh Google Sheet: :title', ['title' => $sub->title]),
            'payload' => ['subscription_id' => $sub->id],
        ]);

        try {
            $sheets->refreshSubscription($sub);
            $sub->update([
                'last_refreshed_at' => now(),
                'next_refresh_at' => now()->addDay(),
            ]);
            $job->markDone('', 'application/vnd.google-apps.spreadsheet');
        } catch (\Throwable $e) {
            $job->markFailed($e->getMessage());
        }
    }
}
