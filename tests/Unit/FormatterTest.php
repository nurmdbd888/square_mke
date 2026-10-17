<?php

namespace Tests\Unit;

use App\Domain\Money\Formatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FormatterTest extends TestCase
{
    public function test_acceptance_example_in_english_and_bangla(): void
    {
        $this->assertSame('12,34,567.89', Formatter::number('1234567.89', locale: 'en'));
        $this->assertSame('১২,৩৪,৫৬৭.৮৯', Formatter::number('1234567.89', locale: 'bn'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function groupings(): array
    {
        return [
            'zero' => ['0', '0.00'],
            'hundreds' => ['999', '999.00'],
            'thousand' => ['1000', '1,000.00'],
            'lakh' => ['100000', '1,00,000.00'],
            'ten lakh' => ['1000000', '10,00,000.00'],
            'crore' => ['10000000', '1,00,00,000.00'],
            'hundred crore' => ['1234567890.5', '1,23,45,67,890.50'],
            'negative' => ['-1234567.89', '-12,34,567.89'],
            'rounds half up' => ['0.125', '0.13'],
            'negative rounds away from zero' => ['-0.125', '-0.13'],
            'existing commas are ignored' => ['12,34,567.89', '12,34,567.89'],
        ];
    }

    #[DataProvider('groupings')]
    public function test_indian_grouping(string $input, string $expected): void
    {
        $this->assertSame($expected, Formatter::number($input, locale: 'en'));
    }

    public function test_scale_and_integers(): void
    {
        $this->assertSame('12,34,568', Formatter::number('1234567.5', 0, 'en'));
        $this->assertSame('1,500', Formatter::number(1500, 0, 'en'));
        $this->assertSame('1.2346', Formatter::number('1.23456', 4, 'en'));
    }

    public function test_bangla_digits_are_accepted_as_input(): void
    {
        $this->assertSame('12,34,567.89', Formatter::number('১২৩৪৫৬৭.৮৯', locale: 'en'));
    }

    public function test_money_has_the_taka_sign(): void
    {
        $this->assertSame('৳ 12,34,567.89', Formatter::money('1234567.89', 'en'));
        $this->assertSame('৳ ১২,৩৪,৫৬৭.৮৯', Formatter::money('1234567.89', 'bn'));
    }

    public function test_compact_uses_crore_and_lakh(): void
    {
        $this->assertSame('1.23 crore', Formatter::compact('12345678', 'en'));
        $this->assertSame('12.35 lakh', Formatter::compact('1234567', 'en'));
        $this->assertSame('45,678', Formatter::compact('45678', 'en'));
        $this->assertSame('১.২৩ কোটি', Formatter::compact('12345678', 'bn'));
        $this->assertSame('-২.৫০ লাখ', Formatter::compact('-250000', 'bn'));
    }

    public function test_digits_convert_both_ways(): void
    {
        $this->assertSame('০১২৩৪৫৬৭৮৯', Formatter::localiseDigits('0123456789', 'bn'));
        $this->assertSame('0123456789', Formatter::localiseDigits('0123456789', 'en'));
        $this->assertSame('2026-27', Formatter::toAsciiDigits('২০২৬-২৭'));
    }
}
