<?php

namespace App\Services\DataServices\Prices;

use App\DataTransferObjects\ServiceObjects\ItemPrices\ItemPricesDto;
use App\DataTransferObjects\ServiceObjects\ItemPrices\ItemPricesQueryDto;

interface LeagueItemPricesServiceContract
{
    /**
     * Prices and traded units of the item by price check within the window counted back from the
     * last price check of the league, the currencies the item was priced in and the summary.
     * An unavailable currency falls back to the most covered one. Cached until the prices of
     * the league are imported again.
     */
    public function prices(string $leagueSlug, string $itemSlug, ItemPricesQueryDto $query): ItemPricesDto;
}
