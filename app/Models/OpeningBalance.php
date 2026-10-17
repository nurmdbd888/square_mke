<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningBalance extends Model
{
    use Auditable;

    public const KINDS = ['stock', 'receivable', 'payable', 'cash', 'bank'];

    protected $fillable = [
        'kind', 'as_of', 'product_id', 'retailer_id', 'supplier_id', 'qty', 'amount', 'note',
    ];

    protected function casts(): array
    {
        return [
            'as_of' => 'date:Y-m-d',
            'qty' => 'decimal:2',
            'amount' => 'decimal:2',
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
}
