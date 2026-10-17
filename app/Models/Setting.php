<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Written only through App\Settings\SettingsStore, which audits each change
 * with the effective old and new values.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'effective_from', 'value', 'updated_by'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }
}
