<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockNote extends Model
{
    use Auditable, SoftDeletes;

    public const TYPE_SALES_RETURN = 'sales_return';

    public const TYPE_STOCK_COUNT = 'stock_count';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_LOSS = 'loss';

    public const TYPE_PURCHASE_RETURN = 'purchase_return';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'type', 'number', 'doc_date', 'status', 'retailer_id', 'supplier_id', 'source_document_id',
        'net_total', 'stock_loss_total', 'notes', 'posted_at', 'voided_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'doc_date' => 'date:Y-m-d',
            'net_total' => 'decimal:2',
            'stock_loss_total' => 'decimal:2',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<StockNoteLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockNoteLine::class)->orderBy('line_no')->orderBy('id');
    }

    /**
     * @return BelongsTo<Retailer, $this>
     */
    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function isSalesReturn(): bool
    {
        return $this->type === self::TYPE_SALES_RETURN;
    }
}
