<?php

namespace App\Repositories\Poe\Exchange\Metrics;

use Carbon\CarbonImmutable;

interface ExchangeBucketPriceRepositoryContract
{
    /**
     * Prices of every item of the league expressed in the currency with the given
     * trade id, grouped by item id, for the price checks created in [from, to). Each
     * sample carries the units traded per hour, null when the volume is unknown.
     *
     * @return array<int, list<array{price: float, units: float|null}>>
     */
    public function getPricesByItem(
        int $leagueId,
        string $currencyTradeId,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array;
}
