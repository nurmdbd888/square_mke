<?php

namespace App\Domain\Documents;

use App\Models\Document;
use Illuminate\Validation\ValidationException;

/**
 * Rejects duplicate non-void document numbers within the same document type.
 */
final class DuplicateGuard
{
    public function assertUnique(Document $document): void
    {
        $query = Document::query()
            ->where('type', $document->type)
            ->where('number', $document->number)
            ->where('status', '!=', Document::STATUS_VOID)
            ->when($document->exists, fn ($q) => $q->where('id', '!=', $document->id));

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'number' => __('A non-void document with this number already exists.'),
            ]);
        }
    }
}
