<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockComparisonLine extends Model
{
    public const STATUS_MATCH = 'match';

    public const STATUS_QTY_DIFF = 'qty_diff';

    public const STATUS_UNKNOWN_PRODUCT = 'unknown_product';

    public const STATUS_ONLY_OURS = 'only_ours';

    protected $fillable = [
        'stock_comparison_id', 'product_code', 'product_name', 'product_id',
        'our_qty', 'their_qty', 'status',
    ];

    protected function casts(): array
    {
        return [
            'our_qty' => 'decimal:3',
            'their_qty' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<StockComparison, $this>
     */
    public function comparison(): BelongsTo
    {
        return $this->belongsTo(StockComparison::class, 'stock_comparison_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
