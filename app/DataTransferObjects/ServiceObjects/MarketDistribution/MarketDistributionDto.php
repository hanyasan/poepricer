<?php

namespace App\DataTransferObjects\ServiceObjects\MarketDistribution;

final readonly class MarketDistributionDto
{
    /**
     * @param  string[]  $buckets  starts of the latest buckets, newest first
     * @param  MarketBinDto[]  $bins
     * @param  MarketCategoryDto[]  $byCategory
     * @param  MarketItemDto[]  $items
     */
    public function __construct(
        public ?string $currency,
        public ?string $bucket,
        public array $buckets,
        public ?float $marketReturn,
        public ?float $median,
        public MarketBreadthDto $breadth,
        public array $bins = [],
        public array $byCategory = [],
        public array $items = [],
    ) {}
}
