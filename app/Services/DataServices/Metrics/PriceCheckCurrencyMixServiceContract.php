<?php

namespace App\Services\DataServices\Metrics;

interface PriceCheckCurrencyMixServiceContract
{
    /**
     * Adds the currencies of the league metrics items that are missing from the
     * core types of the price check, so prices can be measured in them as well.
     */
    public function mixInCurrencies(int $priceCheckId, int $leagueId): void;
}
