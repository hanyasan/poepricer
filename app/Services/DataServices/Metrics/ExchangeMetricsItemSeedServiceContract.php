<?php

namespace App\Services\DataServices\Metrics;

use App\DataTransferObjects\ServiceObjects\ExchangeMetricsMixResultDto;
use App\DataTransferObjects\ServiceObjects\ExchangeMetricsSeedResultDto;

interface ExchangeMetricsItemSeedServiceContract
{
    /**
     * Seeds the metrics items of a league that has none from the core types of its
     * latest price check. The minimum price of a currency is the price above which
     * the top fifth of the items priced in it lies.
     */
    public function seedForLeague(int $leagueId): ExchangeMetricsSeedResultDto;

    /**
     * Adds one currency to the metrics items of a league, also when the league has
     * others. The currency does not have to be among the core types yet: its rate is
     * the reciprocal of its price in the latest price check, as the currency mix does.
     */
    public function mixItemForLeague(int $leagueId, string $tradeId): ExchangeMetricsMixResultDto;
}
