<?php

namespace App\DataTransferObjects\ServiceObjects\ItemPrices;

use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheck\ItemPriceCurrencyRowDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheck\ItemPriceSnapshotRowDto;
use App\Enums\ItemPriceWindowEnum;

final readonly class ItemPricesDto
{
    /**
     * @param  ItemPriceCurrencyRowDto[]  $currencies
     * @param  ItemPriceSnapshotRowDto[]  $points
     */
    public function __construct(
        public ?string $currency,
        public ItemPriceWindowEnum $window,
        public ?string $from,
        public ?string $to,
        public array $currencies = [],
        public array $points = [],
        public ?ItemPriceStatsDto $stats = null,
    ) {}
}
