<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityImportRow extends Model
{
    public const STATUS_OK = 'ok';

    public const STATUS_ERROR = 'error';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'entity_import_batch_id', 'row_no', 'raw', 'parsed', 'status', 'errors',
    ];

    protected function casts(): array
    {
        return [
            'raw' => 'array',
            'parsed' => 'array',
        ];
    }

    public function hasErrors(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    /**
     * @return BelongsTo<EntityImportBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(EntityImportBatch::class, 'entity_import_batch_id');
    }
}
