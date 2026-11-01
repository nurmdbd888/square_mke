<?php

namespace Tests\Unit\Domain\Money;

use App\Domain\Money\InvoiceRounding;
use PHPUnit\Framework\TestCase;

class InvoiceRoundingTest extends TestCase
{
    public function test_it_rounds_fractional_payable_to_whole_units_with_half_down(): void
    {
        $rounding = new InvoiceRounding;

        $this->assertSame('-0.49', $rounding->adjustment(
            [['qty' => '1', 'unit_price' => '10.49', 'line_value' => '10.49']],
            [],
        ));
        $this->assertSame('-0.50', $rounding->adjustment(
            [['qty' => '1', 'unit_price' => '10.50', 'line_value' => '10.50']],
            [],
        ));
        $this->assertSame('0.49', $rounding->adjustment(
            [['qty' => '1', 'unit_price' => '10.51', 'line_value' => '10.51']],
            [],
        ));
        $this->assertSame('0.40', $rounding->adjustment(
            [['qty' => '1', 'unit_price' => '10.60', 'line_value' => '10.60']],
            [],
        ));
    }

    public function test_it_includes_invoice_adjustments_and_ignores_combo_bonus_lines(): void
    {
        $adjustment = (new InvoiceRounding)->adjustment([
            ['kind' => 'normal', 'line_value' => '10.60'],
            ['kind' => 'combo_bonus', 'line_value' => '100.00'],
        ], [
            'header_discount' => '0.10',
            'slab_adjustment' => '0.10',
            'combo_bonus_discount' => '0.10',
            'tds_amount' => '0.30',
        ]);

        $this->assertSame('0.40', $adjustment);
    }
}
