<?php

namespace App\Services\DataServices\Metrics;

use Carbon\CarbonImmutable;

interface ExchangeMetricsServiceContract
{
    /**
     * Collects the basket, item and market metrics of one time bucket for every
     * metrics currency of the league. Runs once per bucket and is safe to repeat.
     */
    public function collectBucketMetrics(int $leagueId, CarbonImmutable $bucketStart): void;

    /**
     * Recalculates and stores the beta and R² of every basket item over the window
     * that ends at the given moment. Runs once per day.
     */
    public function recalculateBetas(int $leagueId, CarbonImmutable $asOf): void;
}
