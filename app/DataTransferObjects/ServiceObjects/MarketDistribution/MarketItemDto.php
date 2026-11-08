<?php

namespace App\DataTransferObjects\ServiceObjects\MarketDistribution;

final readonly class MarketItemDto
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $category,
        public float $price,
        public float $logReturn,
        public ?float $units,
        public ?float $returnWeight,
    ) {}
}
