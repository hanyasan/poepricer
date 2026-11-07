<?php

namespace App\Services\DataServices\Metrics;

use App\DataTransferObjects\ServiceObjects\ExchangeMetricsSeedResultDto;

interface ExchangeMetricsItemSeedServiceContract
{
    /**
     * Seeds the metrics items of a league that has none from the core types of its
     * latest price check. The minimum price of a currency is the price above which
     * the top fifth of the items priced in it lies.
     */
    public function seedForLeague(int $leagueId): ExchangeMetricsSeedResultDto;
}
