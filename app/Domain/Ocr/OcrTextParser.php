<?php

namespace App\Domain\Ocr;

/**
 * Heuristic field extraction from OCR text (receipts / invoices).
 * Mirrored in public/js/ocr-scan.js for in-browser apply.
 */
final class OcrTextParser
{
    /**
     * @return array{amount: ?string, date: ?string, reference: ?string, invoice_number: ?string, party: ?string, raw: string}
     */
    public function parse(string $text, string $mode = 'receipt'): array
    {
        $normalized = $this->normalizeDigits($text);
        $flat = preg_replace('/[ \t]+/', ' ', $normalized) ?? $normalized;

        return [
            'amount' => $this->extractAmount($flat),
            'date' => $this->extractDate($flat),
            'reference' => $this->extractReference($flat),
            'invoice_number' => $this->extractInvoiceNumber($flat),
            'party' => $mode === 'invoice' ? $this->extractParty($flat) : null,
            'raw' => trim($normalized),
        ];
    }

    public function normalizeDigits(string $text): string
    {
        $bn = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($bn, $en, $text);
    }

    private function extractAmount(string $text): ?string
    {
        $patterns = [
            '/(?:amount|total|received|taka|tk|৳)\s*[:\-]?\s*((?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d{1,2})?)/iu',
            '/((?:\d{1,3}(?:,\d{3})+|\d+)\.\d{2})\b/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return $this->normalizeMoney($m[1]);
            }
        }

        return null;
    }

    private function extractDate(string $text): ?string
    {
        if (! preg_match('/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})\b/', $text, $m)) {
            return null;
        }
        $d = (int) $m[1];
        $mo = (int) $m[2];
        $y = (int) $m[3];
        if ($y < 100) {
            $y += 2000;
        }
        if ($d < 1 || $d > 31 || $mo < 1 || $mo > 12) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }

    private function extractReference(string $text): ?string
    {
        if (preg_match('/(?:ref(?:erence)?|cheque|chq|trx|txn)\s*[#.:]?\s*([A-Z0-9\-\/]{3,40})/iu', $text, $m)) {
            return strtoupper(trim($m[1]));
        }

        return null;
    }

    private function extractInvoiceNumber(string $text): ?string
    {
        if (preg_match('/(?:invoice|inv|bill|challan)\s*(?:no\.?|number|#)?\s*[:.]?\s*([A-Z0-9\-\/]{2,40})/iu', $text, $m)) {
            return strtoupper(trim($m[1]));
        }

        return null;
    }

    private function extractParty(string $text): ?string
    {
        if (preg_match('/(?:retailer|customer|party|supplier|sold to|bill to)\s*[:\-]?\s*([^\n\r]{3,80})/iu', $text, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    private function normalizeMoney(string $value): string
    {
        $clean = str_replace(',', '', trim($value));
        if (! is_numeric($clean)) {
            return $clean;
        }

        return number_format((float) $clean, 2, '.', '');
    }
}
