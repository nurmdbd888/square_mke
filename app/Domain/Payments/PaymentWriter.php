<?php

namespace App\Domain\Payments;

use App\Domain\Money\Exact;
use App\Models\Document;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates, posts, voids and clears receipts and payments.
 */
final class PaymentWriter
{
    public function __construct(private readonly PartyBalance $balances) {}

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array{document_id: int, amount: string|int|float}>  $allocations
     */
    public function save(array $header, array $allocations = [], ?User $by = null, ?Payment $existing = null): Payment
    {
        return DB::transaction(function () use ($header, $allocations, $by, $existing) {
            $payment = $existing ?? new Payment(['status' => Payment::STATUS_DRAFT]);

            if ($existing?->isVoid()) {
                throw ValidationException::withMessages(['status' => __('A void payment cannot be edited.')]);
            }

            $wasPosted = $existing?->isPosted() ?? false;
            $method = (string) $header['method'];
            $kind = (string) $header['kind'];
            $amount = Exact::money($header['amount'] ?? 0);

            if (Exact::of($amount)->isZero() || Exact::of($amount)->isNegative()) {
                throw ValidationException::withMessages(['amount' => __('Amount must be greater than zero.')]);
            }

            $payment->fill([
                'kind' => $kind,
                'number' => trim((string) $header['number']),
                'retailer_id' => $kind === Payment::KIND_RECEIPT ? ($header['retailer_id'] ?? null) : null,
                'supplier_id' => $kind === Payment::KIND_PAYMENT ? ($header['supplier_id'] ?? null) : null,
                'method' => $method,
                'amount' => $amount,
                'payment_date' => Carbon::parse($header['payment_date'])->toDateString(),
                'due_date' => empty($header['due_date']) ? null : Carbon::parse($header['due_date'])->toDateString(),
                'reference' => isset($header['reference']) ? (string) $header['reference'] : null,
                'cheque_number' => isset($header['cheque_number']) ? (string) $header['cheque_number'] : null,
                'sales_rep_id' => $header['sales_rep_id'] ?? null,
                'expense_category_id' => $kind === Payment::KIND_PAYMENT ? ($header['expense_category_id'] ?? null) : null,
                'is_advance' => (bool) ($header['is_advance'] ?? false),
                'notes' => isset($header['notes']) ? (string) $header['notes'] : null,
                'updated_by' => $by?->id,
            ]);

            if ($kind === Payment::KIND_RECEIPT) {
                if (! $payment->retailer_id) {
                    throw ValidationException::withMessages(['retailer_id' => __('Choose a retailer.')]);
                }
            }

            if ($method === 'pdc') {
                if ($payment->due_date === null) {
                    throw ValidationException::withMessages(['due_date' => __('A post-dated cheque needs a due date.')]);
                }
                if (! $payment->exists || $payment->clearing_status === null) {
                    $payment->clearing_status = Payment::CLEARING_PENDING;
                }
            } else {
                $payment->clearing_status = null;
                $payment->cleared_at = null;
                $payment->due_date = $method === 'cheque' ? $payment->due_date : null;
            }

            if (! $payment->exists) {
                $payment->created_by = $by?->id;
                $payment->status = Payment::STATUS_DRAFT;
            }

            $payment->save();
            $this->syncAllocations($payment, $allocations);

            $unallocated = Exact::of($payment->amount)->minus(
                Exact::of($payment->allocations()->sum('amount'))
            );
            if ($unallocated->isPositive()) {
                $payment->is_advance = true;
                $payment->save();
            }

            $action = (string) ($header['action'] ?? 'save');
            if ($action === 'post' && ! $wasPosted) {
                $this->post($payment->fresh('allocations'), $by);
            } elseif ($wasPosted) {
                $this->post($payment->fresh('allocations'), $by, quiet: true);
                AuditLogger::log('payment.posted_edited', $payment, null, [
                    'number' => $payment->number,
                    'status' => Payment::STATUS_POSTED,
                ], $by);
            }

            return $payment->fresh(['allocations.document', 'retailer', 'supplier', 'expenseCategory']);
        });
    }

    public function post(Payment $payment, ?User $by = null, bool $quiet = false): Payment
    {
        if ($payment->isVoid()) {
            throw ValidationException::withMessages(['status' => __('A void payment cannot be posted.')]);
        }

        $allocated = Exact::of($payment->allocations()->sum('amount'));
        if ($allocated->isGreaterThan(Exact::of($payment->amount))) {
            throw ValidationException::withMessages(['allocations' => __('Allocations cannot exceed the payment amount.')]);
        }

        $payment->status = Payment::STATUS_POSTED;
        $payment->posted_at = $payment->posted_at ?? now();
        $payment->voided_at = null;
        $payment->updated_by = $by?->id;
        if ($allocated->isLessThan(Exact::of($payment->amount))) {
            $payment->is_advance = true;
        }
        $payment->save();

        if (! $quiet) {
            AuditLogger::log('payment.posted', $payment, null, [
                'number' => $payment->number,
                'amount' => Exact::money($payment->amount),
            ], $by);
        }

        return $payment->fresh('allocations');
    }

    public function void(Payment $payment, ?User $by = null): Payment
    {
        if ($payment->isVoid()) {
            return $payment;
        }

        if ($payment->isDraft()) {
            $payment->allocations()->delete();
            $payment->delete();

            return $payment;
        }

        $payment->forceFill([
            'status' => Payment::STATUS_VOID,
            'voided_at' => now(),
            'updated_by' => $by?->id,
        ])->save();

        AuditLogger::log('payment.voided', $payment, ['status' => Payment::STATUS_POSTED], [
            'status' => Payment::STATUS_VOID,
        ], $by);

        return $payment->fresh();
    }

    public function clearPdc(Payment $payment, ?User $by = null, ?string $clearedOn = null): Payment
    {
        if (! $payment->isPdc() || ! $payment->isPosted()) {
            throw ValidationException::withMessages(['method' => __('Only a posted post-dated cheque can be cleared.')]);
        }

        if ($payment->clearing_status === Payment::CLEARING_CLEARED) {
            return $payment;
        }

        $payment->forceFill([
            'clearing_status' => Payment::CLEARING_CLEARED,
            'cleared_at' => $clearedOn ? Carbon::parse($clearedOn)->startOfDay() : now(),
            'updated_by' => $by?->id,
        ])->save();

        AuditLogger::log('payment.cleared', $payment, ['clearing_status' => Payment::CLEARING_PENDING], [
            'clearing_status' => Payment::CLEARING_CLEARED,
            'cleared_at' => $payment->cleared_at?->toDateString(),
        ], $by);

        return $payment->fresh();
    }

    /**
     * @param  list<array{document_id: int, amount: string|int|float}>  $allocations
     */
    private function syncAllocations(Payment $payment, array $allocations): void
    {
        $payment->allocations()->delete();
        $total = Exact::of('0');

        foreach ($allocations as $row) {
            $amount = Exact::of($row['amount'] ?? 0);
            if (! $amount->isPositive()) {
                continue;
            }

            $document = Document::query()->findOrFail((int) $row['document_id']);
            if ($document->status !== Document::STATUS_POSTED) {
                throw ValidationException::withMessages(['allocations' => __('Only posted documents can be allocated.')]);
            }

            if ($payment->isReceipt() && (! $document->isSale() || $document->retailer_id !== $payment->retailer_id)) {
                throw ValidationException::withMessages(['allocations' => __('Allocate receipts only to that retailer\'s sales.')]);
            }
            if ($payment->isPayment() && $payment->supplier_id && (! $document->isPurchase() || $document->supplier_id !== $payment->supplier_id)) {
                throw ValidationException::withMessages(['allocations' => __('Allocate supplier payments only to that supplier\'s purchases.')]);
            }

            $already = Exact::of(
                PaymentAllocation::query()
                    ->where('document_id', $document->id)
                    ->whereHas('payment', fn ($q) => $q->where('status', Payment::STATUS_POSTED)->where('id', '!=', $payment->id))
                    ->sum('amount')
            );
            $remaining = Exact::of($document->net_total)->minus($already);
            if ($amount->isGreaterThan($remaining)) {
                throw ValidationException::withMessages([
                    'allocations' => __('Allocation on :number exceeds the remaining balance.', ['number' => $document->number]),
                ]);
            }

            PaymentAllocation::query()->create([
                'payment_id' => $payment->id,
                'document_id' => $document->id,
                'amount' => Exact::money($amount),
            ]);
            $total = $total->plus($amount);
        }

        if ($total->isGreaterThan(Exact::of($payment->amount))) {
            throw ValidationException::withMessages(['allocations' => __('Allocations cannot exceed the payment amount.')]);
        }
    }
}
