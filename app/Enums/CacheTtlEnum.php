<?php

namespace App\Enums;

enum CacheTtlEnum: int
{
    case MINUTE = 60;
    case HOUR = 3600;
    case DAY = 86400;

    public function jiggle(): int
    {
        return $this->value + rand(1, 15);
    }
}
