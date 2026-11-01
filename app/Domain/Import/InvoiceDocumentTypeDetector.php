<?php

namespace App\Domain\Import;

use App\Models\DocumentImportBatch;

final class InvoiceDocumentTypeDetector
{
    /**
     * @param  list<array{text: string}>  $pages
     */
    public function detect(array $pages): ?string
    {
        $text = mb_strtolower(implode("\n", array_column($pages, 'text')));
        $purchase = 0;
        $sale = 0;

        foreach ([
            '/\b(?:purchase|supplier|vendor)\s+(?:tax\s+)?invoice\b/u' => 4,
            '/\b(?:supplier|vendor)\s+name\b/u' => 4,
            '/\b(?:supplier|vendor|buyer)\s*:/u' => 3,
            '/\b(?:purchase\s+order|challan)\b/u' => 3,
            '/\b(?:buyer|supplier)\s+(?:name|code|id)\b/u' => 2,
        ] as $pattern => $weight) {
            $purchase += preg_match($pattern, $text) === 1 ? $weight : 0;
        }

        foreach ([
            '/\b(?:sales|sale)\s+(?:tax\s+)?invoice\b/u' => 4,
            '/\bretailer\s+(?:name|code|id)\b/u' => 4,
            '/\bretailer\b/u' => 4,
            '/\b(?:sales\s+representative|sales\s+rep|distributor)\b/u' => 2,
            '/\broute\s+(?:code|name)\b/u' => 1,
        ] as $pattern => $weight) {
            $sale += preg_match($pattern, $text) === 1 ? $weight : 0;
        }

        if ($purchase === $sale || max($purchase, $sale) < 3) {
            return null;
        }

        return $purchase > $sale
            ? DocumentImportBatch::TYPE_PURCHASE
            : DocumentImportBatch::TYPE_SALE;
    }
}
