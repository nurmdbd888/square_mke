<?php

namespace App\Domain\Returns;

use App\Domain\Money\Exact;
use App\Models\Claim;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClaimWriter
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data, ?User $by = null, ?Claim $existing = null): Claim
    {
        return DB::transaction(function () use ($data, $by, $existing) {
            $claim = $existing ?? new Claim;

            if ($existing !== null && in_array($existing->status, [Claim::STATUS_SETTLED, Claim::STATUS_REJECTED], true)) {
                throw ValidationException::withMessages(['status' => __('This claim cannot be edited.')]);
            }

            $claim->fill([
                'number' => trim((string) $data['number']),
                'doc_date' => Carbon::parse($data['doc_date'])->toDateString(),
                'supplier_id' => $data['supplier_id'] ?? null,
                'amount' => Exact::money(Exact::of($data['amount'] ?? 0)),
                'description' => isset($data['description']) ? (string) $data['description'] : null,
                'notes' => isset($data['notes']) ? (string) $data['notes'] : null,
                'updated_by' => $by?->id,
            ]);

            if (! $claim->exists) {
                $claim->status = Claim::STATUS_OPEN;
                $claim->created_by = $by?->id;
            }

            if (isset($data['status']) && in_array($data['status'], Claim::STATUSES, true)) {
                $this->transition($claim, (string) $data['status']);
            }

            $claim->save();

            return $claim->fresh('supplier');
        });
    }

    public function transition(Claim $claim, string $to): void
    {
        $from = $claim->status;
        if ($from === $to) {
            return;
        }

        $allowed = [
            Claim::STATUS_OPEN => [Claim::STATUS_SUBMITTED, Claim::STATUS_REJECTED],
            Claim::STATUS_SUBMITTED => [Claim::STATUS_APPROVED, Claim::STATUS_REJECTED],
            Claim::STATUS_APPROVED => [Claim::STATUS_SETTLED, Claim::STATUS_REJECTED],
            Claim::STATUS_SETTLED => [],
            Claim::STATUS_REJECTED => [],
        ];

        if (! in_array($to, $allowed[$from] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('Invalid claim status change.')]);
        }

        $claim->status = $to;

        if ($to === Claim::STATUS_SUBMITTED && $claim->submitted_at === null) {
            $claim->submitted_at = now();
        }
        if ($to === Claim::STATUS_SETTLED) {
            $claim->settled_at = now();
        }

        AuditLogger::log('claim.status_changed', $claim, ['status' => $from], ['status' => $to]);
    }
}
