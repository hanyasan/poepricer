<?php

namespace App\Repositories\Poe\Exchange\Metrics;

use Carbon\CarbonImmutable;

interface ExchangeMetricsRepositoryContract
{
    /**
     * Ids of the items that are in the basket of the bucket.
     *
     * @return array<int, int>
     */
    public function getBasketItemIds(int $metricsItemId, CarbonImmutable $bucketStart): array;

    /**
     * @param  array<int, array{
     *     exchange_item_id: int,
     *     exchange_metrics_item_id: int,
     *     bucket_start: string,
     *     price: float,
     *     log_return: float|null,
     *     residual: float|null,
     *     residual_mad: float|null,
     *     observations: int,
     * }>  $rows
     */
    public function upsertItemMetrics(array $rows): void;

    /**
     * @param  array{
     *     poe_league_id: int,
     *     exchange_metrics_item_id: int,
     *     bucket_start: string,
     *     market_return: float,
     *     basket_size: int,
     * }  $row
     */
    public function upsertMarketMetric(array $row): void;

    /**
     * Latest beta of every item calculated at or before the moment.
     *
     * @return array<int, float>
     */
    public function getLatestBetas(int $metricsItemId, CarbonImmutable $asOf): array;

    /**
     * Residuals of the items for the buckets in [from, to), oldest first.
     *
     * @return array<int, array<int, float>>
     */
    public function getResidualHistory(int $metricsItemId, CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * Item and market returns of every item for the buckets in [from, to), oldest first.
     *
     * @return array<int, array<int, array{market: float, item: float}>>
     */
    public function getReturnPairs(int $metricsItemId, CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * @param  array<int, array{
     *     exchange_item_id: int,
     *     exchange_metrics_item_id: int,
     *     calculated_at: string,
     *     beta: float,
     *     raw_beta: float,
     *     r_squared: float|null,
     *     observations: int,
     * }>  $rows
     */
    public function upsertBetas(array $rows): void;
}
