<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SmokeHeartbeat extends Model
{
    public $timestamps = false;

    protected $fillable = ['ran_at', 'source', 'php_version', 'php_binary', 'hostname'];

    protected function casts(): array
    {
        return [
            'ran_at' => 'datetime',
        ];
    }

    /**
     * Latest heartbeat, or null when the database or table is not ready yet.
     */
    public static function latestOrNull(): ?self
    {
        try {
            if (! Schema::hasTable((new self)->getTable())) {
                return null;
            }

            return self::query()->orderByDesc('ran_at')->orderByDesc('id')->first();
        } catch (Throwable) {
            return null;
        }
    }

    public function isFresh(): bool
    {
        return $this->ran_at->greaterThanOrEqualTo(now()->subMinutes((int) config('sdm.heartbeat.fresh_minutes')));
    }
}
