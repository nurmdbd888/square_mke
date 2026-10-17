<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentImportBatch extends Model
{
    public const STATUS_PREVIEW = 'preview';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_POSTED = 'posted';

    public const STATUS_UNDONE = 'undone';

    public const STATUS_FAILED = 'failed';

    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_SALE = 'sale';

    protected $fillable = [
        'doc_type', 'layout', 'original_name', 'stored_path', 'status',
        'invoice_count', 'line_count', 'error_count', 'mapping_profile_id',
        'meta', 'header_warnings', 'posted_document_ids', 'posted_at', 'undone_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'header_warnings' => 'array',
            'posted_document_ids' => 'array',
            'posted_at' => 'datetime',
            'undone_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<DocumentImportInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(DocumentImportInvoice::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<ImportMappingProfile, $this>
     */
    public function mappingProfile(): BelongsTo
    {
        return $this->belongsTo(ImportMappingProfile::class, 'mapping_profile_id');
    }

    public function isPurchase(): bool
    {
        return $this->doc_type === self::TYPE_PURCHASE;
    }
}
