<?php

namespace App\Enums;

enum MetricPeriodEnum: string
{
    case Day = '24h';
    case Week = '7d';
    case League = 'league';

    /**
     * Length of the period in hours, null for the whole league.
     */
    public function hours(): ?int
    {
        return match ($this) {
            self::Day => 24,
            self::Week => 24 * 7,
            self::League => null,
        };
    }
}
