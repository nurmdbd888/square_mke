<?php

namespace Tests\Unit;

use App\Domain\Units\Converter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UnitConverterTest extends TestCase
{
    public function test_dozen_and_carton_become_pieces(): void
    {
        $this->assertSame('10.00', Converter::toPieces(10, Converter::PIECE));
        $this->assertSame('24.00', Converter::toPieces(2, Converter::DOZEN));
        $this->assertSame('18.00', Converter::toPieces('1.5', Converter::DOZEN));
        $this->assertSame('48.00', Converter::toPieces(2, Converter::CARTON, 24));
    }

    public function test_a_carton_without_a_factor_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Converter::toPieces(1, Converter::CARTON, null);
    }
}
