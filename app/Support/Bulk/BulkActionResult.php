<?php

namespace App\Support\Bulk;

final class BulkActionResult
{
    public int $applied = 0;

    public int $skipped = 0;

    /** @var list<string> */
    public array $errors = [];

    public function message(): string
    {
        if ($this->errors !== []) {
            return __('Applied :applied. Skipped :skipped. :count errors.', [
                'applied' => $this->applied,
                'skipped' => $this->skipped,
                'count' => count($this->errors),
            ]);
        }

        if ($this->skipped > 0) {
            return __('Applied :applied. Skipped :skipped.', [
                'applied' => $this->applied,
                'skipped' => $this->skipped,
            ]);
        }

        return __('Applied to :count rows.', ['count' => $this->applied]);
    }
}
