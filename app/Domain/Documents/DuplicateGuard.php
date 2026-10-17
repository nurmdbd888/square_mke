<?php

namespace App\Domain\Documents;

use App\Domain\Money\Exact;
use App\Models\Document;
use Illuminate\Validation\ValidationException;

/**
 * Rejects a second non-void document with the same number, party and net total.
 */
final class DuplicateGuard
{
    public function assertUnique(Document $document): void
    {
        $query = Document::query()
            ->where('type', $document->type)
            ->where('number', $document->number)
            ->where('net_total', Exact::money($document->net_total))
            ->where('status', '!=', Document::STATUS_VOID)
            ->when($document->exists, fn ($q) => $q->where('id', '!=', $document->id));

        if ($document->isPurchase()) {
            $query->where('supplier_id', $document->supplier_id);
        } else {
            $query->where('retailer_id', $document->retailer_id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'number' => __('A document with this number, party and total already exists.'),
            ]);
        }
    }
}
