<?php

namespace App\Enums;

enum ItemPriceWindowEnum: string
{
    case SixHours = '6h';
    case Day = '24h';
    case TwoDays = '48h';
    case Week = '7d';

    /**
     * Length of the window in hours.
     */
    public function hours(): int
    {
        return match ($this) {
            self::SixHours => 6,
            self::Day => 24,
            self::TwoDays => 48,
            self::Week => 24 * 7,
        };
    }
}
