<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCheckLine extends Model
{
    public const IN_STORE_AVAILABLE = 'available';

    public const IN_STORE_NOT_AVAILABLE = 'not_available';

    protected $fillable = [
        'stock_check_id', 'product_id', 'in_store', 'on_hand_qty', 'on_hand_value',
    ];

    protected function casts(): array
    {
        return [
            'on_hand_qty' => 'decimal:3',
            'on_hand_value' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<StockCheck, $this>
     */
    public function stockCheck(): BelongsTo
    {
        return $this->belongsTo(StockCheck::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
