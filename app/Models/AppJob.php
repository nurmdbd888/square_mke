<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppJob extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id', 'type', 'status', 'progress', 'label', 'payload',
        'result_path', 'result_mime', 'error', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markRunning(): void
    {
        $this->update(['status' => self::STATUS_RUNNING, 'progress' => 5]);
    }

    public function markProgress(int $percent): void
    {
        $this->update(['progress' => min(99, max(0, $percent))]);
    }

    public function markDone(string $path, string $mime): void
    {
        $this->update([
            'status' => self::STATUS_DONE,
            'progress' => 100,
            'result_path' => $path,
            'result_mime' => $mime,
            'finished_at' => now(),
        ]);
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error' => $message,
            'finished_at' => now(),
        ]);
    }
}
