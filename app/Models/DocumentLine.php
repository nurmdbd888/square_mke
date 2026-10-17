<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentLine extends Model
{
    public const KIND_NORMAL = 'normal';

    public const KIND_COMBO_BONUS = 'combo_bonus';

    protected $fillable = [
        'document_id', 'line_no', 'product_id', 'kind', 'qty', 'bonus_qty', 'unit_price',
        'line_discount', 'header_discount_share', 'line_value', 'sd_pct', 'vat_pct',
        'sd_amount', 'vat_amount', 'issue_cost', 'bonus_issue_cost', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'bonus_qty' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'line_discount' => 'decimal:2',
            'header_discount_share' => 'decimal:2',
            'line_value' => 'decimal:2',
            'sd_pct' => 'decimal:4',
            'vat_pct' => 'decimal:4',
            'sd_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'issue_cost' => 'decimal:2',
            'bonus_issue_cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isComboBonus(): bool
    {
        return $this->kind === self::KIND_COMBO_BONUS;
    }
}
