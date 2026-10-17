<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockNoteLine extends Model
{
    public const CONDITION_GOOD = 'good';

    public const CONDITION_DAMAGED = 'damaged';

    public const CONDITION_EXPIRED = 'expired';

    protected $fillable = [
        'stock_note_id', 'line_no', 'product_id', 'product_batch_id', 'line_condition', 'qty',
        'expected_qty', 'unit_price', 'line_value', 'linked_document_line_id', 'restore_cost',
        'loss_cost', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'expected_qty' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'line_value' => 'decimal:2',
            'restore_cost' => 'decimal:2',
            'loss_cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<StockNote, $this>
     */
    public function stockNote(): BelongsTo
    {
        return $this->belongsTo(StockNote::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<DocumentLine, $this>
     */
    public function linkedDocumentLine(): BelongsTo
    {
        return $this->belongsTo(DocumentLine::class, 'linked_document_line_id');
    }

    public function isDamagedOrExpired(): bool
    {
        return in_array($this->line_condition, [self::CONDITION_DAMAGED, self::CONDITION_EXPIRED], true);
    }
}
