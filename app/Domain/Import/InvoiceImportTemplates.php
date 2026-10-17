<?php

namespace App\Domain\Import;

use App\Models\DocumentImportBatch;

final class InvoiceImportTemplates
{
    public const PROFILE_PURCHASE = 'purchase_invoice';

    public const PROFILE_SALE = 'sale_invoice';

    /**
     * @return list<string>
     */
    public static function headerColumns(string $docType): array
    {
        return $docType === DocumentImportBatch::TYPE_PURCHASE
            ? [
                'invoice_no', 'doc_date', 'doc_time', 'supplier_name', 'supplier_bin',
                'buyer_name', 'buyer_bin', 'vehicle_type', 'vehicle_no',
                'total_qty', 'total_value', 'total_sd', 'total_vat', 'grand_total',
            ]
            : [
                'invoice_no', 'invoice_date', 'delivery_date', 'distributor_name', 'distributor_code',
                'route_name', 'route_code', 'sr_name', 'sr_code',
                'retailer_name', 'retailer_code', 'retailer_contact',
                'subtotal', 'slab_adjustment', 'combo_bonus_policy', 'rounding_adjustment', 'net_total',
                'rep_collection',
            ];
    }

    /**
     * @return list<string>
     */
    public static function lineColumns(string $docType): array
    {
        return $docType === DocumentImportBatch::TYPE_PURCHASE
            ? [
                'invoice_no', 'serial', 'product_code', 'product_description', 'unit', 'qty', 'bonus_qty',
                'unit_price_ex_tax', 'line_discount', 'value_ex_tax', 'sd_pct', 'sd_amount',
                'vat_pct', 'vat_amount', 'total_incl_tax', 'batch_no', 'expiry', 'notes',
            ]
            : [
                'invoice_no', 'section', 'line_type', 'serial', 'product_code', 'product_name', 'pack_size',
                'unit_price', 'qty', 'prod_discount', 'bonus_qty', 'amount', 'parent_serial', 'notes',
            ];
    }

    /**
     * @return list<string>
     */
    public static function flatColumns(string $docType): array
    {
        return array_values(array_unique(array_merge(
            ['doc_type'],
            self::headerColumns($docType),
            array_diff(self::lineColumns($docType), ['invoice_no']),
        )));
    }

    /**
     * @return array<string, list<string>>
     */
    public static function lineHeaderAliases(string $docType): array
    {
        if ($docType === DocumentImportBatch::TYPE_PURCHASE) {
            return [
                'product_description' => ['product_description', 'Product Description', 'description'],
                'unit_price_ex_tax' => ['unit_price_ex_tax', 'unit_price', 'Unit Price', 'unit price'],
                'value_ex_tax' => ['value_ex_tax', 'value', 'Value'],
                'total_incl_tax' => ['total_incl_tax', 'total_incl_vat', 'total'],
                'serial' => ['serial', 'Serial', 'S/N', 'serial_no'],
            ];
        }

        return [
            'product_name' => ['product_name', 'product_name_bn', 'Product Name', 'product_description'],
            'prod_discount' => ['prod_discount', 'line_discount', 'Prod Discount', 'discount'],
            'line_type' => ['line_type', 'Line Type'],
            'section' => ['section', 'Section'],
        ];
    }
}
