<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleSheetSubscription extends Model
{
    protected $fillable = [
        'created_by', 'export_key', 'title', 'spreadsheet_id', 'filters',
        'columns', 'refresh', 'last_refreshed_at', 'next_refresh_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'columns' => 'array',
            'last_refreshed_at' => 'datetime',
            'next_refresh_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
