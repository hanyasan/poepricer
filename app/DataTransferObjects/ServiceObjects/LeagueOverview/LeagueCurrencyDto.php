<?php

namespace App\DataTransferObjects\ServiceObjects\LeagueOverview;

final readonly class LeagueCurrencyDto
{
    public function __construct(
        public string $slug,
        public string $name,
    ) {}
}
