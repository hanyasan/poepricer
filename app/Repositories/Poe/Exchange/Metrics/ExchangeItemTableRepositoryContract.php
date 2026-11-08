<?php

namespace App\Repositories\Poe\Exchange\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemPricePointDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemTableRowDto;
use App\DataTransferObjects\RepositoryObjects\PaginatedResultDto;
use App\DataTransferObjects\ServiceObjects\LeagueItems\ItemsFilterDto;
use App\DataTransferObjects\ServiceObjects\LeagueItems\ItemsSortDto;
use App\DataTransferObjects\ServiceObjects\PaginationDto;
use Carbon\CarbonImmutable;

interface ExchangeItemTableRepositoryContract
{
    /**
     * A page of the items of the league with their metrics of the bucket and their
     * latest beta. Items without metrics in the bucket are kept with null metrics.
     * Rows without the sorted value go last, ties are broken by the item id.
     *
     * @return PaginatedResultDto<ItemTableRowDto>
     */
    public function paginate(
        int $leagueId,
        int $metricsItemId,
        CarbonImmutable $bucketStart,
        ItemsFilterDto $filter,
        ItemsSortDto $sort,
        PaginationDto $pagination,
    ): PaginatedResultDto;

    /**
     * Prices of the items in the buckets after the moment, oldest first, by item id.
     *
     * @param  array<int, int>  $itemIds
     * @return array<int, ItemPricePointDto[]>
     */
    public function getPriceSeries(int $metricsItemId, array $itemIds, CarbonImmutable $after): array;
}
