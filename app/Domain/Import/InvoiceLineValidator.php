<?php

namespace App\Domain\Import;

use App\Domain\Money\Exact;
use App\Models\DocumentImportBatch;
use Brick\Math\RoundingMode;

final class InvoiceLineValidator
{
    /**
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function validate(string $docType, array $line): array
    {
        $errors = [];
        $warnings = [];

        if ($docType === DocumentImportBatch::TYPE_PURCHASE) {
            return $this->validatePurchaseLine($line);
        }

        return $this->validateSaleLine($line);
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{errors: list<string>, warnings: list<string>}
     */
    private function validatePurchaseLine(array $line): array
    {
        $errors = [];
        $warnings = [];
        $qty = Exact::of($line['qty'] ?? 0);
        if ($qty->isZero() && Exact::of($line['bonus_qty'] ?? 0)->isZero()) {
            $errors[] = __('Qty is required.');

            return compact('errors', 'warnings');
        }

        $unit = Exact::of($line['unit_price'] ?? 0);
        $value = Exact::of($line['line_value'] ?? 0);
        if (! $qty->isZero() && $line['line_value'] !== null) {
            $expected = $qty->multipliedBy($unit);
            $tol = Exact::of('0.01');
            $qtyTol = $qty->multipliedBy(Exact::of('0.005'));
            if ($qtyTol->isGreaterThan($tol)) {
                $tol = $qtyTol;
            }
            $diff = $expected->minus($value)->abs();
            if ($diff->isGreaterThan($tol)) {
                $warnings[] = __('Unit price × qty differs from value by :diff (tolerance :tol).', [
                    'diff' => Exact::money($diff),
                    'tol' => Exact::money($tol),
                ]);
            }
        }

        $sdPct = Exact::of($line['sd_pct'] ?? 0);
        $sdAmt = Exact::of($line['sd_amount'] ?? 0);
        if (! $sdPct->isZero() && $line['line_value'] !== null) {
            $expectedSd = $value->multipliedBy($sdPct)->dividedBy(Exact::of('100'), 2, RoundingMode::HalfUp);
            if ($expectedSd->minus($sdAmt)->abs()->isGreaterThan(Exact::of('0.02'))) {
                $warnings[] = __('SD amount may not match value × SD%.');
            }
        }

        $vatPct = Exact::of($line['vat_pct'] ?? 15);
        $vatAmt = Exact::of($line['vat_amount'] ?? 0);
        if ($line['line_value'] !== null) {
            $base = $value->plus($sdAmt);
            $expectedVat = $base->multipliedBy($vatPct)->dividedBy(Exact::of('100'), 2, RoundingMode::HalfUp);
            $vatDiff = $expectedVat->minus($vatAmt)->abs();
            if ($vatDiff->isGreaterThan(Exact::of('0.02'))) {
                $warnings[] = __('VAT amount may not match (value + SD) × VAT%.');
            } elseif ($vatDiff->isEqualTo(Exact::of('0.01'))) {
                $warnings[] = __('VAT is off by 0.01 (printed total rounding).');
            }
        }

        return compact('errors', 'warnings');
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{errors: list<string>, warnings: list<string>}
     */
    private function validateSaleLine(array $line): array
    {
        $errors = [];
        $warnings = [];
        $type = (string) ($line['line_type'] ?? 'invoice');

        if ($type === 'combo_bonus') {
            if (Exact::of($line['amount'] ?? 0)->isGreaterThan(Exact::of('0'))) {
                $warnings[] = __('Combo bonus lines should have amount 0.');
            }

            return compact('errors', 'warnings');
        }

        if ($type === 'bonus') {
            return compact('errors', 'warnings');
        }

        $qty = Exact::of($line['qty'] ?? 0);
        $price = Exact::of($line['unit_price'] ?? 0);
        $disc = Exact::of($line['line_discount'] ?? 0);
        $amount = Exact::of($line['line_value'] ?? 0);
        $expected = $qty->multipliedBy($price)->minus($disc);
        if (! $expected->minus($amount)->abs()->isLessThanOrEqualTo(Exact::of('0.01'))) {
            $errors[] = __('Qty × price − discount must equal amount.');
        }

        return compact('errors', 'warnings');
    }
}
