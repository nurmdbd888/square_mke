<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntityImportBatch extends Model
{
    public const STATUS_PREVIEW = 'preview';

    public const STATUS_POSTED = 'posted';

    public const STATUS_UNDONE = 'undone';

    public const MODE_UPDATE = 'update';

    public const MODE_SKIP = 'skip';

    protected $fillable = [
        'entity', 'original_name', 'status', 'duplicate_mode', 'row_count', 'error_count',
        'mapping_profile_id', 'summary', 'posted_at', 'undone_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'posted_at' => 'datetime',
            'undone_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<EntityImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(EntityImportRow::class);
    }

    /**
     * @return BelongsTo<ImportMappingProfile, $this>
     */
    public function mappingProfile(): BelongsTo
    {
        return $this->belongsTo(ImportMappingProfile::class, 'mapping_profile_id');
    }
}
