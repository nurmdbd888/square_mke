<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use Auditable, SoftDeletes;

    public const KIND_RECEIPT = 'receipt';

    public const KIND_PAYMENT = 'payment';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_VOID = 'void';

    public const METHODS = ['cash', 'bkash', 'nagad', 'rocket', 'bank', 'cheque', 'pdc', 'petty'];

    public const CASH_METHODS = ['cash', 'bkash', 'nagad', 'rocket', 'petty'];

    public const BANK_METHODS = ['bank', 'cheque', 'pdc'];

    public const CLEARING_PENDING = 'pending';

    public const CLEARING_CLEARED = 'cleared';

    public const CLEARING_BOUNCED = 'bounced';

    protected $fillable = [
        'kind', 'number', 'retailer_id', 'supplier_id', 'method', 'amount', 'payment_date', 'due_date',
        'clearing_status', 'cleared_at', 'reference', 'cheque_number', 'sales_rep_id',
        'expense_category_id', 'is_advance', 'status', 'notes', 'posted_at', 'voided_at',
        'created_by', 'updated_by',
    ];

    /** @var list<string> */
    protected array $auditIgnore = ['posted_at', 'voided_at', 'cleared_at'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'is_advance' => 'boolean',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
            'cleared_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * @return BelongsToMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseOrder::class, 'purchase_order_payments')->withTimestamps();
    }

    /**
     * @return HasMany<PaymentAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(PaymentAttachment::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Payment $payment) {
            $payment->loadMissing('attachments');
            foreach ($payment->attachments as $file) {
                $file->deleteFile();
            }
        });
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

    /**
     * @return BelongsTo<SalesRep, $this>
     */
    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(SalesRep::class);
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function isReceipt(): bool
    {
        return $this->kind === self::KIND_RECEIPT;
    }

    public function isPayment(): bool
    {
        return $this->kind === self::KIND_PAYMENT;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function isPdc(): bool
    {
        return $this->method === 'pdc';
    }

    /**
     * Whether this row moves cash/bank books on a given date.
     * PDC (and uncleared cheques marked pending) count only after cleared.
     */
    public function countsInBooks(): bool
    {
        if (! $this->isPosted()) {
            return false;
        }

        if ($this->method === 'pdc') {
            return $this->clearing_status === self::CLEARING_CLEARED;
        }

        return true;
    }

    public function bookDate(): ?string
    {
        if (! $this->countsInBooks()) {
            return null;
        }

        if ($this->method === 'pdc' && $this->cleared_at !== null) {
            return $this->cleared_at->toDateString();
        }

        return $this->payment_date?->toDateString();
    }

    public function isCashMethod(): bool
    {
        return in_array($this->method, self::CASH_METHODS, true);
    }

    public function isBankMethod(): bool
    {
        return in_array($this->method, self::BANK_METHODS, true);
    }

    public function partyLabel(): string
    {
        if ($this->retailer_id) {
            return (string) ($this->retailer?->name ?? '');
        }
        if ($this->supplier_id) {
            return (string) ($this->supplier?->name ?? '');
        }
        if ($this->expense_category_id) {
            return (string) ($this->expenseCategory?->label ?? __('Expense'));
        }

        return '';
    }
}
