<?php

namespace App\Domain\Units;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Piece, dozen (12) and carton (the product's units_per_carton) quantities.
 */
final class Converter
{
    public const PIECE = 'Pcs';

    public const DOZEN = 'dozen';

    public const CARTON = 'carton';

    public static function toPieces(BigDecimal|int|string $qty, string $unit, ?int $unitsPerCarton = null): string
    {
        $quantity = BigDecimal::of($qty);
        $factor = match ($unit) {
            self::DOZEN => '12',
            self::CARTON => (string) ($unitsPerCarton ?? 0),
            default => '1',
        };

        if ($unit === self::CARTON && ($unitsPerCarton === null || $unitsPerCarton < 1)) {
            throw new \InvalidArgumentException('This product has no units per carton.');
        }

        return (string) $quantity->multipliedBy($factor)->toScale(2, RoundingMode::HalfUp);
    }
}
