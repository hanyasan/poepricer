<?php

namespace App\Repositories\Poe\Exchange\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeItemBetaDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemMetricRowDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemSeriesRowDto;
use App\DataTransferObjects\RepositoryObjects\PaginatedResultDto;
use App\DataTransferObjects\ServiceObjects\PaginationDto;
use Carbon\CarbonImmutable;

interface ExchangeItemDetailRepositoryContract
{
    /**
     * Metrics of the item in the bucket, null when the item has none there.
     */
    public function findMetric(int $metricsItemId, int $itemId, CarbonImmutable $bucketStart): ?ItemMetricRowDto;

    /**
     * Latest beta of the item, null when it was never calculated.
     */
    public function findLatestBeta(int $metricsItemId, int $itemId): ?ExchangeItemBetaDto;

    /**
     * Betas of the item calculated after the moment, oldest first. All betas when the
     * moment is null.
     *
     * @return ExchangeItemBetaDto[]
     */
    public function getBetaHistory(int $metricsItemId, int $itemId, ?CarbonImmutable $after): array;

    /**
     * Price and residual of the item in the buckets after the moment, oldest first.
     * All buckets when the moment is null.
     *
     * @return ItemSeriesRowDto[]
     */
    public function getSeries(int $metricsItemId, int $itemId, ?CarbonImmutable $after): array;

    /**
     * A page of the metrics of the item in the buckets after the moment, newest first.
     * All buckets when the moment is null.
     *
     * @return PaginatedResultDto<ItemMetricRowDto>
     */
    public function paginateMetrics(
        int $metricsItemId,
        int $itemId,
        ?CarbonImmutable $after,
        PaginationDto $pagination,
    ): PaginatedResultDto;
}
