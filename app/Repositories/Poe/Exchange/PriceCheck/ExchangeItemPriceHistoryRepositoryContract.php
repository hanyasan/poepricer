<?php

namespace App\Repositories\Poe\Exchange\PriceCheck;

use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheck\ItemPriceCurrencyRowDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheck\ItemPriceSnapshotRowDto;
use Carbon\CarbonImmutable;

interface ExchangeItemPriceHistoryRepositoryContract
{
    /**
     * Currencies the item was priced in within the window: the core types of the price check
     * cores that hold the item, without duplicates. The item itself is not one of them. Most
     * covered first, then by trade id.
     *
     * @return ItemPriceCurrencyRowDto[]
     */
    public function getCurrencies(
        int $leagueId,
        int $itemId,
        string $itemTradeId,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array;

    /**
     * Price and traded units per hour of the item in the currency, one row per price check that
     * priced the item in it, oldest first. Unknown volume counts as zero units.
     *
     * @return ItemPriceSnapshotRowDto[]
     */
    public function getPoints(
        int $leagueId,
        int $itemId,
        string $currency,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array;
}
