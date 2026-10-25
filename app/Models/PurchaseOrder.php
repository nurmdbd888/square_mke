<?php

namespace App\Models;

use App\Domain\Money\Exact;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'number', 'supplier_id', 'order_date', 'expected_delivery_date', 'notes', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date:Y-m-d',
            'expected_delivery_date' => 'date:Y-m-d',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('line_no')->orderBy('id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceipt::class)->orderBy('received_on')->orderBy('id');
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'purchase_order_payments')
            ->withTimestamps()
            ->orderBy('payments.payment_date')
            ->orderBy('payments.id');
    }

    public function totalAmount(): string
    {
        $total = Exact::of('0');
        foreach ($this->lines as $line) {
            $lineTotal = Exact::money(Exact::of($line->qty)->multipliedBy(Exact::of($line->unit_price)));
            $total = $total->plus(Exact::of($lineTotal));
        }

        return Exact::money($total);
    }

    public function paidAmount(): string
    {
        $total = Exact::of('0');
        foreach ($this->payments as $payment) {
            if ($payment->isPosted()) {
                $total = $total->plus(Exact::of($payment->amount));
            }
        }

        return Exact::money($total);
    }

    public function isFullyPaid(): bool
    {
        $total = Exact::of($this->totalAmount());

        return ! $total->isPositive() || ! Exact::of($this->paidAmount())->isLessThan($total);
    }

    public function remainingPayment(): string
    {
        return Exact::money(Exact::of($this->totalAmount())->minus(Exact::of($this->paidAmount())));
    }

    public function receivedQuantity(): string
    {
        $total = Exact::of('0');
        foreach ($this->lines as $line) {
            $total = $total->plus(Exact::of($line->received_qty));
        }

        return Exact::qty($total);
    }

    public function statusLabel(): string
    {
        if ($this->isFullyReceived()) {
            return $this->isFullyPaid() ? __('Received') : __('Received / Payment Due');
        }

        $received = Exact::of($this->receivedQuantity())->isPositive();
        if ($received) {
            return __('Partially Received');
        }

        if ($this->isFullyPaid()) {
            return __('Paid / Waiting for Arrival');
        }

        return Exact::of($this->paidAmount())->isPositive()
            ? __('Partially Paid / Waiting for Arrival')
            : __('Pending');
    }

    public function isFullyReceived(): bool
    {
        return $this->lines->isNotEmpty()
            && $this->lines->every(fn (PurchaseOrderLine $line) => ! Exact::of($line->received_qty)->isLessThan(Exact::of($line->qty)));
    }
}
