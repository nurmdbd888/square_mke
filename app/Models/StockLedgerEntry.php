<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLedgerEntry extends Model
{
    protected $fillable = [
        'product_id', 'product_batch_id', 'doc_date', 'movement', 'rank',
        'document_id', 'document_line_id', 'stock_note_id', 'stock_note_line_id',
        'qty_in', 'qty_out', 'value_in', 'value_out', 'running_qty', 'running_value',
        'average_cost', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'doc_date' => 'date:Y-m-d',
            'qty_in' => 'decimal:3',
            'qty_out' => 'decimal:3',
            'value_in' => 'decimal:2',
            'value_out' => 'decimal:2',
            'running_qty' => 'decimal:3',
            'running_value' => 'decimal:2',
            'average_cost' => 'decimal:6',
            'posted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<DocumentLine, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(DocumentLine::class, 'document_line_id');
    }

    /**
     * @return BelongsTo<StockNote, $this>
     */
    public function stockNote(): BelongsTo
    {
        return $this->belongsTo(StockNote::class);
    }

    /**
     * @return BelongsTo<ProductBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }
}
