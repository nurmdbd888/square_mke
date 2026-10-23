<?php

namespace App\Domain\Import;

use App\Models\DocumentImportBatch;
use Illuminate\Validation\ValidationException;

final class SquareInvoiceOcrParser
{
    /**
     * @param  list<array{text: string}>  $pages
     * @return array{layout: string, invoices: list<array{invoice_no: string, header: array<string, mixed>, lines: list<array<string, mixed>>}>}
     */
    public function parse(string $docType, array $pages): array
    {
        $this->assertDocType($docType);
        $invoices = [];
        $lastPurchaseInvoice = null;
        $lastPurchaseStoreUnit = false;
        $layout = $docType === DocumentImportBatch::TYPE_SALE ? 'ocr_sales' : 'ocr_purchase';

        foreach ($pages as $pageIndex => $page) {
            $text = trim(InvoiceText::normalizeDigits((string) ($page['text'] ?? '')));
            if ($text === '') {
                continue;
            }

            $header = $this->extractHeader($docType, $text);
            $hasStoreUnitHeader = preg_match('/\b(?:SKU\s*Code|Issued\s*Qty)\b/iu', $text) === 1;
            if ($docType === DocumentImportBatch::TYPE_PURCHASE) {
                if ($hasStoreUnitHeader) {
                    $lastPurchaseStoreUnit = true;
                } elseif (! empty($header['invoice_no']) && $header['invoice_no'] !== $lastPurchaseInvoice) {
                    $lastPurchaseStoreUnit = false;
                }
            }
            $storeUnit = $docType === DocumentImportBatch::TYPE_PURCHASE
                && $lastPurchaseStoreUnit;
            if ($storeUnit) {
                $layout = 'ocr_challan';
            } elseif ($docType === DocumentImportBatch::TYPE_PURCHASE) {
                $layout = 'ocr_supplier_invoice';
            }

            $number = trim((string) ($header['invoice_no'] ?? ''));
            if ($number === '' && $docType === DocumentImportBatch::TYPE_PURCHASE) {
                $number = (string) $lastPurchaseInvoice;
            }
            if ($number === '') {
                $number = 'OCR-'.now()->format('YmdHis').'-'.($pageIndex + 1);
            }
            if ($docType === DocumentImportBatch::TYPE_PURCHASE) {
                $lastPurchaseInvoice = $number;
            }

            $invoices[$number] ??= [
                'invoice_no' => $number,
                'header' => ['invoice_no' => $number],
                'lines' => [],
            ];
            $invoices[$number]['header'] = array_merge(
                $invoices[$number]['header'],
                array_filter($header, fn ($value) => $value !== null && $value !== ''),
            );

            $lines = $storeUnit
                ? $this->parseStoreUnitRows($text)
                : $this->parsePricedRows($text, $docType);
            $seenProductCodes = array_fill_keys(array_column($invoices[$number]['lines'], 'product_code'), true);
            foreach ($lines as $line) {
                if ($storeUnit && ! empty($line['product_code'])) {
                    $code = $line['product_code'];
                    if (isset($seenProductCodes[$code])) {
                        $line['bonus_qty'] = '0';
                    }
                    $seenProductCodes[$code] = true;
                }
                $invoices[$number]['lines'][] = $line;
            }
        }

        $invoices = array_values(array_filter($invoices, fn ($invoice) => $invoice['lines'] !== []));
        if ($invoices === []) {
            throw ValidationException::withMessages([
                'scan_files' => __('No invoice line items could be read. Try a clearer image or PDF, or use the spreadsheet importer.'),
            ]);
        }

        return ['layout' => $layout, 'invoices' => $invoices];
    }

    /**
     * @return array<string, string|null>
     */
    private function extractHeader(string $docType, string $text): array
    {
        $header = [];
        $header['invoice_no'] = $this->firstMatch(
            $text,
            '/^\s*(?:invoice|bill)\s*(?:no\.?|number|#)\s*[:#-]?\s*([A-Z0-9][A-Z0-9\/_-]*)/imu',
        );
        $header['doc_date'] = $this->firstMatch(
            $text,
            '/^\s*(?:invoice\s*)?date\s*[:#-]?\s*(\d{1,4}[\/.-]\d{1,2}[\/.-]\d{1,4})/imu',
        );

        if ($docType === DocumentImportBatch::TYPE_PURCHASE) {
            $header['supplier_name'] = $this->firstMatch(
                $text,
                '/^\s*(?:supplier|vendor)\s*(?:name)?\s*[:#-]\s*(.+?)\s*$/imu',
            );
            $header['buyer_name'] = $this->firstMatch(
                $text,
                '/^\s*(?:buyer|customer)\s*(?:name)?\s*[:#-]\s*(.+?)\s*$/imu',
            );
            $header['slab_adjustment'] = $this->moneyAfterLabel($text, 'loading(?:\\s+adjustment)?');
            $header['combo_bonus_discount'] = $this->moneyAfterLabel($text, 'combo(?:\\s+bonus)?\\s+discount');
            $header['tds_amount'] = $this->moneyAfterLabel($text, 'T\\.?D\\.?S\\.?');
            $header['net_total'] = $this->moneyAfterLabel($text, '(?:total\\s+payable|net\\s+invoice\\s+amount)');
            $header['source_total'] = $this->moneyAfterLabel($text, '(?:total\\s+price|invoice\\s+total)');
        } else {
            $header['invoice_date'] = $header['doc_date'];
            $header['retailer_code'] = $this->firstMatch(
                $text,
                '/^\s*(?:retailer|customer)\s*(?:code|id)\s*[:#-]\s*([A-Z0-9_-]+)/imu',
            );
            $header['retailer_name'] = $this->firstMatch(
                $text,
                '/^\s*(?:retailer|customer)\s*name\s*[:#-]\s*(.+?)\s*$/imu',
            );
            $header['distributor_name'] = $this->firstMatch(
                $text,
                '/^\s*(?:distributor|seller)\s*(?:name)?\s*[:#-]\s*(.+?)\s*$/imu',
            );
            $header['slab_adjustment'] = $this->moneyAfterLabel($text, 'slab\\s+adjustment');
            $header['combo_bonus_policy'] = $this->moneyAfterLabel($text, 'combo(?:\\s+bonus)?\\s+discount');
            $header['rounding_adjustment'] = $this->moneyAfterLabel($text, 'round(?:ing)?\\s+adjustment');
            $header['net_total'] = $this->moneyAfterLabel($text, '(?:net\\s+total|total\\s+payable)');
        }

        return $header;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseStoreUnitRows(string $text): array
    {
        $lines = [];
        $bonusSeen = [];
        foreach ($this->textLines($text) as $row) {
            if (! preg_match(
                '/^\s*\d+\s+([A-Z][A-Z0-9_-]{1,})\s+(.+?)\s+(\d+(?:\.\d+)?\s*(?:ml|g|gm|kg|l|ltr|pc|pcs|tablet|capsule|[\'’]s))\s+([\d,]+)\s+([\d,]+)\s+([\d,]+)\s+([\d,]+)\s*Box\s*,?\s*([\d,]+)\s*Pcs\s+([\d,]+)\s+([\d,]+(?:\.\d{1,2})?)\s+([A-Z0-9_-]{5,})\s+([\d,]+)\s+.*$/iu',
                $row,
                $m,
            )) {
                continue;
            }

            $code = strtoupper($m[1]);
            $qty = $this->number($m[12]) ?? $this->number($m[9]) ?? '0';
            $bonus = $bonusSeen[$code] ?? '0';
            $bonusSeen[$code] = true;

            $lines[] = [
                'product_code' => $code,
                'product_label' => trim($m[2]),
                'pack_size' => trim($m[3]),
                'qty' => $qty,
                'bonus_qty' => $bonus === true ? '0' : ($this->number($m[5]) ?? '0'),
                'unit_price' => null,
                'line_discount' => '0',
                'line_value' => null,
                'batch_no' => trim($m[11]),
                'mrp' => $this->number($m[10]),
                'use_product_buy_price' => true,
            ];
        }

        return $lines;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parsePricedRows(string $text, string $docType): array
    {
        $lines = [];
        $bonusIsQuantity = $docType === DocumentImportBatch::TYPE_SALE
            && preg_match('/\bbonus\s*(?:qty|quantity)\b/iu', $text) === 1;
        foreach ($this->textLines($text) as $row) {
            if (preg_match('/\b(?:subtotal|grand\s+total|total\s+payable|net\s+invoice|invoice\s+total)\b/iu', $row)) {
                continue;
            }
            $m = null;
            $matched = false;
            $hasBonusAmount = false;
            $hasDiscount = true;
            $patterns = [
                [
                    'pattern' => '/^\s*(?:\d+\s+)?(?<label>.+?)\s+(?<qty>-?[\d,]+(?:\.\d+)?)\s+(?<price>-?[\d,]+(?:\.\d{1,4})?)\s+(?<discount>-?[\d,]+(?:\.\d{1,2})?)\s+(?<bonus>-?[\d,]+(?:\.\d{1,2})?)\s+(?<value>-?[\d,]+(?:\.\d{1,2})?)\s*$/u',
                    'bonus' => true,
                    'discount' => true,
                ],
                [
                    'pattern' => '/^\s*(?:\d+\s+)?(?<label>.+?)\s+(?<qty>-?[\d,]+(?:\.\d+)?)\s+(?<price>-?[\d,]+(?:\.\d{1,4})?)\s+(?<discount>-?[\d,]+(?:\.\d{1,2})?)\s+(?<value>-?[\d,]+(?:\.\d{1,2})?)\s*$/u',
                    'bonus' => false,
                    'discount' => true,
                ],
                [
                    'pattern' => '/^\s*(?:\d+\s+)?(?<label>.+?)\s+(?<qty>-?[\d,]+(?:\.\d+)?)\s+(?<price>-?[\d,]+(?:\.\d{1,4})?)\s+(?<value>-?[\d,]+(?:\.\d{1,2})?)\s*$/u',
                    'bonus' => false,
                    'discount' => false,
                ],
            ];
            foreach ($patterns as $candidate) {
                if (preg_match($candidate['pattern'], $row, $m) === 1) {
                    $hasBonusAmount = $candidate['bonus'];
                    $hasDiscount = $candidate['discount'];
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                continue;
            }

            $description = trim($m['label']);
            $code = null;
            if (preg_match('/^([A-Z][A-Z0-9_-]{1,})\s+(.+)$/u', $description, $codeMatch)) {
                $code = strtoupper($codeMatch[1]);
                $description = trim($codeMatch[2]);
            }
            $packSize = null;
            if (preg_match('/\s+(\d+(?:\.\d+)?\s*(?:ml|g|gm|kg|l|ltr|pc|pcs|tablet|capsule|[\'’]s))$/iu', $description, $packMatch)) {
                $packSize = trim($packMatch[1]);
                $description = trim(substr($description, 0, -strlen($packMatch[0])));
            }

            $lines[] = [
                'product_code' => $code,
                'product_label' => $description,
                'pack_size' => $packSize,
                'qty' => $this->number($m['qty']) ?? '0',
                'bonus_qty' => $hasBonusAmount && $bonusIsQuantity ? ($this->number($m['bonus']) ?? '0') : '0',
                'unit_price' => $this->number($m['price']),
                'line_discount' => $hasDiscount ? ($this->number($m['discount']) ?? '0') : '0',
                'line_value' => $this->number($m['value']),
                'batch_no' => null,
                'mrp' => null,
                'use_product_buy_price' => false,
                'line_type' => 'invoice',
            ];
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function textLines(string $text): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/u', $text) ?: []),
            fn (string $line) => $line !== '',
        ));
    }

    private function firstMatch(string $text, string $pattern): ?string
    {
        if (preg_match($pattern, $text, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    private function moneyAfterLabel(string $text, string $label): ?string
    {
        return $this->firstMatch(
            $text,
            '/^\s*'.$label.'\s*[:#-]?\s*(?:BDT|Tk\.?)?\s*(-?[\d,]+(?:\.\d{1,2})?)/imu',
        );
    }

    private function number(string $value): ?string
    {
        return InvoiceText::parseNumber($value);
    }

    private function assertDocType(string $docType): void
    {
        if (! in_array($docType, [DocumentImportBatch::TYPE_PURCHASE, DocumentImportBatch::TYPE_SALE], true)) {
            throw ValidationException::withMessages(['doc_type' => __('Invalid document type.')]);
        }
    }
}
