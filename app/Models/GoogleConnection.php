<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleConnection extends Model
{
    protected $fillable = [
        'connected_by', 'google_email', 'access_token', 'refresh_token',
        'token_expires_at', 'drive_folder_id',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function connector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public static function current(): ?self
    {
        return self::query()->orderByDesc('id')->first();
    }
}
