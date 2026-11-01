<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderReceipt extends Model
{
    protected $fillable = [
        'purchase_order_id', 'number', 'received_on', 'challan_no', 'vehicle_no', 'notes', 'document_id', 'received_by',
    ];

    protected function casts(): array
    {
        return ['received_on' => 'date:Y-m-d'];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceiptLine::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
