<?php

namespace App\DataTransferObjects\ServiceObjects\ItemPrices;

use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheck\ItemPriceSnapshotRowDto;

final readonly class ItemPriceStatsDto
{
    /**
     * @param  ItemPriceChangeDto[]  $changes
     */
    public function __construct(
        public ItemPriceSnapshotRowDto $current,
        public float $min,
        public float $max,
        public float $median,
        public float $averageUnits,
        public array $changes,
    ) {}
}
