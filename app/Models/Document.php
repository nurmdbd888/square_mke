<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use Auditable, SoftDeletes;

    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_SALE = 'sale';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_VOID = 'void';

    public const COLLECTION_DUE = 'due';

    public const COLLECTION_PARTIAL = 'partial';

    public const COLLECTION_COMPLETED = 'completed';

    protected $fillable = [
        'type', 'number', 'supplier_id', 'retailer_id', 'doc_date', 'delivery_date', 'status',
        'collection_status', 'collection_confirmed_at',
        'sales_rep_id', 'route_id', 'vehicle_id',
        'header_discount', 'slab_adjustment', 'combo_bonus_discount', 'tds_amount', 'rounding_adjustment',
        'subtotal', 'net_total', 'revenue', 'cogs', 'free_goods_cost',
        'commission_gross', 'commission_vat', 'commission_net', 'notes',
        'posted_at', 'voided_at', 'created_by', 'updated_by',
    ];

    /** @var list<string> */
    protected array $auditIgnore = [
        'revenue', 'cogs', 'free_goods_cost', 'commission_gross', 'commission_vat', 'commission_net',
        'posted_at', 'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'doc_date' => 'date:Y-m-d',
            'delivery_date' => 'date:Y-m-d',
            'header_discount' => 'decimal:2',
            'slab_adjustment' => 'decimal:2',
            'combo_bonus_discount' => 'decimal:2',
            'tds_amount' => 'decimal:2',
            'rounding_adjustment' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'net_total' => 'decimal:2',
            'revenue' => 'decimal:2',
            'cogs' => 'decimal:2',
            'free_goods_cost' => 'decimal:2',
            'commission_gross' => 'decimal:2',
            'commission_vat' => 'decimal:2',
            'commission_net' => 'decimal:2',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
            'collection_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<DocumentLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(DocumentLine::class)->orderBy('line_no')->orderBy('id');
    }

    /**
     * @return HasMany<DocumentAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(DocumentAttachment::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Retailer, $this>
     */
    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class);
    }

    /**
     * @return BelongsTo<SalesRep, $this>
     */
    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(SalesRep::class);
    }

    /**
     * @return BelongsTo<SalesRoute, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(SalesRoute::class, 'route_id');
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function isPurchase(): bool
    {
        return $this->type === self::TYPE_PURCHASE;
    }

    public function isSale(): bool
    {
        return $this->type === self::TYPE_SALE;
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

    public function partyId(): ?int
    {
        return $this->isPurchase() ? $this->supplier_id : $this->retailer_id;
    }

    public function partyLabel(): string
    {
        if ($this->isPurchase()) {
            return (string) ($this->supplier?->name ?? '');
        }

        return (string) ($this->retailer?->name ?? '');
    }
}
