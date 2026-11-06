<?php

namespace App\Repositories\Poe\Exchange\Metrics;

use Carbon\CarbonImmutable;

interface ExchangeBucketPriceRepositoryContract
{
    /**
     * Prices of every item of the league expressed in the currency with the given
     * trade id, grouped by item id, for the price checks created in [from, to).
     *
     * @return array<int, array<int, float>>
     */
    public function getPricesByItem(
        int $leagueId,
        string $currencyTradeId,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array;
}
