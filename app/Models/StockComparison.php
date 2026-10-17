<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockComparison extends Model
{
    protected $fillable = ['original_name', 'created_by'];

    /**
     * @return HasMany<StockComparisonLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockComparisonLine::class);
    }
}
