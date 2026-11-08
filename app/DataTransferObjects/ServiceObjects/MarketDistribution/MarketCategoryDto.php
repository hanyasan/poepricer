<?php

namespace App\DataTransferObjects\ServiceObjects\MarketDistribution;

final readonly class MarketCategoryDto
{
    public function __construct(
        public ?string $category,
        public float $median,
        public int $count,
    ) {}
}
