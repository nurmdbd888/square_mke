<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentImportLine extends Model
{
    public const TYPE_INVOICE = 'invoice';

    public const TYPE_BONUS = 'bonus';

    public const TYPE_COMBO_BONUS = 'combo_bonus';

    protected $fillable = [
        'document_import_invoice_id', 'line_no', 'line_type', 'section',
        'product_id', 'product_code', 'product_label', 'pack_size', 'batch_no', 'mrp', 'provisional_cost',
        'qty', 'bonus_qty', 'unit_price', 'line_discount', 'line_value',
        'sd_pct', 'sd_amount', 'vat_pct', 'vat_amount', 'total_incl_tax',
        'errors', 'warnings',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'bonus_qty' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'line_discount' => 'decimal:2',
            'line_value' => 'decimal:2',
            'sd_pct' => 'decimal:4',
            'sd_amount' => 'decimal:2',
            'vat_pct' => 'decimal:4',
            'vat_amount' => 'decimal:2',
            'mrp' => 'decimal:2',
            'errors' => 'array',
            'warnings' => 'array',
            'provisional_cost' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<DocumentImportInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(DocumentImportInvoice::class, 'document_import_invoice_id');
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
        return $this->line_type === self::TYPE_COMBO_BONUS;
    }
}
