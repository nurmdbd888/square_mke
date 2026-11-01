<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'image_path', 'purchase_discount_pct'];

    protected function casts(): array
    {
        return ['purchase_discount_pct' => 'decimal:2'];
    }

    public function productTypes(): HasMany
    {
        return $this->hasMany(ProductType::class)->orderBy('name');
    }
}
