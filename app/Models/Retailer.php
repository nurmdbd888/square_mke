<?php

namespace App\Models;

use App\Domain\Phone\Normalizer;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Retailer extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'address', 'phone', 'route_id', 'sales_rep_id', 'price_list_id', 'credit_limit', 'custom',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'custom' => 'array',
        ];
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = Normalizer::normalise($value);
    }

    /**
     * @return BelongsTo<SalesRoute, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(SalesRoute::class);
    }

    /**
     * @return BelongsTo<SalesRep, $this>
     */
    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(SalesRep::class);
    }

    /**
     * @return BelongsTo<PriceList, $this>
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }
}
