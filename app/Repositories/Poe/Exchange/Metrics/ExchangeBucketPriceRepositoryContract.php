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
     * @return array<int, list<array{price: float, units: float|null, volume: float|null}>>
     */
    public function getPricesByItem(
        int $leagueId,
        string $currencyTradeId,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array;

    /**
     * Prices of the items of the latest price check of the league expressed in the
     * currency with the given trade id, with the moment the check was created. This is
     * the freshest data, kept apart from the buckets that only ever hold closed spans.
     *
     * @return array{snapshot_at: string|null, prices: array<int, float>}
     */
    public function getLatestPrices(int $leagueId, string $currencyTradeId): array;
}
