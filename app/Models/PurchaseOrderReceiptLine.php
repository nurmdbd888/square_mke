<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderReceiptLine extends Model
{
    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_DUE = 'due';

    protected $fillable = [
        'purchase_order_receipt_id', 'purchase_order_line_id', 'received_qty', 'due_qty', 'status',
    ];

    protected function casts(): array
    {
        return [
            'received_qty' => 'decimal:3',
            'due_qty' => 'decimal:3',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderReceipt::class, 'purchase_order_receipt_id');
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id');
    }
}
