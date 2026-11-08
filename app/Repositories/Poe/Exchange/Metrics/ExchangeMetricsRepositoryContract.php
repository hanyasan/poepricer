<?php

namespace App\Repositories\Poe\Exchange\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeItemMetricDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMarketMetricDto;
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
     *     units: float|null,
     *     return_weight: float|null,
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
     *     basket_total: int,
     * }  $row
     */
    public function upsertMarketMetric(array $row): void;

    /**
     * Latest beta of every item calculated at or before the moment. Only the given
     * items are returned when the list is not empty.
     *
     * @param  array<int, int>  $itemIds
     * @return array<int, float>
     */
    public function getLatestBetas(int $metricsItemId, CarbonImmutable $asOf, array $itemIds = []): array;

    /**
     * Residuals of the items for the buckets in [from, to), oldest first.
     *
     * @return array<int, array<int, float>>
     */
    public function getResidualHistory(int $metricsItemId, CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * Item and market returns of every item for the buckets in [from, to), oldest first.
     * Returns whose weight is zero are left out.
     *
     * @return array<int, array<int, array{market: float, item: float}>>
     */
    public function getReturnPairs(int $metricsItemId, CarbonImmutable $from, CarbonImmutable $to): array;

    public function findLatestMarketMetric(int $metricsItemId): ?ExchangeMarketMetricDto;

    public function findMarketMetric(int $metricsItemId, CarbonImmutable $bucketStart): ?ExchangeMarketMetricDto;

    /**
     * Starts of the latest buckets that have a market metric, newest first.
     *
     * @return string[]
     */
    public function getMarketBucketStarts(int $metricsItemId, int $limit): array;

    /**
     * Metrics of the items of the bucket whose return is calculated. Items with a
     * zero return weight are left out when only reliable returns are asked for.
     *
     * @return ExchangeItemMetricDto[]
     */
    public function getBucketReturns(int $metricsItemId, CarbonImmutable $bucketStart, bool $onlyReliableReturns): array;

    /**
     * Market metrics of the buckets after the moment, oldest first. All buckets when
     * the moment is null.
     *
     * @return ExchangeMarketMetricDto[]
     */
    public function getMarketSeries(int $metricsItemId, ?CarbonImmutable $after): array;

    /**
     * Items of the bucket that grew the most, by log return, best first.
     *
     * @return ExchangeItemMetricDto[]
     */
    public function getTopGainers(int $metricsItemId, CarbonImmutable $bucketStart, int $limit): array;

    /**
     * Items of the bucket that fell the most, by log return, worst first.
     *
     * @return ExchangeItemMetricDto[]
     */
    public function getTopLosers(int $metricsItemId, CarbonImmutable $bucketStart, int $limit): array;

    /**
     * Items of the bucket whose absolute residual exceeds the residual MAD by the
     * multiplier, the largest ratio first.
     *
     * @return ExchangeItemMetricDto[]
     */
    public function getUnusualMoves(
        int $metricsItemId,
        CarbonImmutable $bucketStart,
        float $multiplier,
        int $limit,
    ): array;

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
