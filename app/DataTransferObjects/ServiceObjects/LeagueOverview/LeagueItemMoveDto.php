<?php

namespace App\DataTransferObjects\ServiceObjects\LeagueOverview;

final readonly class LeagueItemMoveDto
{
    public function __construct(
        public string $slug,
        public string $name,
        public float $price,
        public float $logReturn,
    ) {}
}
