<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentImportInvoice extends Model
{
    public const STATUS_OK = 'ok';

    public const STATUS_ERROR = 'error';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_POSTED = 'posted';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'document_import_batch_id', 'invoice_no', 'header', 'warnings', 'errors',
        'status', 'document_id', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'header' => 'array',
            'warnings' => 'array',
            'errors' => 'array',
        ];
    }

    /**
     * @return BelongsTo<DocumentImportBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(DocumentImportBatch::class, 'document_import_batch_id');
    }

    /**
     * @return HasMany<DocumentImportLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(DocumentImportLine::class)->orderBy('line_no');
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
