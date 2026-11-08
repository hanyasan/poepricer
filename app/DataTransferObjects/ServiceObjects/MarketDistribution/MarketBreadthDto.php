<?php

namespace App\DataTransferObjects\ServiceObjects\MarketDistribution;

final readonly class MarketBreadthDto
{
    public function __construct(
        public int $up,
        public int $down,
        public int $flat,
    ) {}
}
