<?php

namespace Tests\Unit;

use App\Domain\Phone\Normalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    /**
     * @return array<string, array{?string, ?string}>
     */
    public static function numbers(): array
    {
        return [
            'local without zero' => ['1711459077', '01711459077'],
            'country code' => ['8801711459077', '01711459077'],
            'plus and dashes' => ['+880 1711-459077', '01711459077'],
            'already local' => ['01711459077', '01711459077'],
            'empty' => ['', null],
            'blank' => ['   ', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_bangladesh_mobiles_are_stored_as_01_numbers(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, Normalizer::normalise($input));
    }
}
