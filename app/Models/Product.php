<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'code', 'name_en', 'name_bn', 'brand', 'pack_size', 'unit', 'units_per_carton',
        'buy_price', 'buy_price_exact', 'sell_price', 'reorder_level', 'sd_pct', 'vat_pct',
        'tracks_expiry', 'custom',
    ];

    protected function casts(): array
    {
        return [
            'units_per_carton' => 'integer',
            'buy_price' => 'decimal:4',
            'buy_price_exact' => 'decimal:4',
            'sell_price' => 'decimal:4',
            'reorder_level' => 'decimal:2',
            'sd_pct' => 'decimal:2',
            'vat_pct' => 'decimal:2',
            'tracks_expiry' => 'boolean',
            'custom' => 'array',
        ];
    }

    /**
     * @return HasMany<ProductAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(ProductAlias::class);
    }
}
