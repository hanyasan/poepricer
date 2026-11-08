<?php

namespace App\Services\DataServices\Metrics;

use App\DataTransferObjects\ServiceObjects\ItemDetail\ItemBucketsPageDto;
use App\DataTransferObjects\ServiceObjects\ItemDetail\ItemDetailQueryDto;
use App\DataTransferObjects\ServiceObjects\ItemDetail\ItemHistoryDto;
use App\DataTransferObjects\ServiceObjects\ItemDetail\ItemSummaryDto;
use App\DataTransferObjects\ServiceObjects\PaginationDto;

interface LeagueItemDetailServiceContract
{
    /**
     * Card of the item: description, metrics of the last bucket and the latest beta.
     * Cached until the metrics, betas, metrics currencies or categories of the league change.
     */
    public function summary(string $leagueSlug, string $itemSlug, ?string $currency): ItemSummaryDto;

    /**
     * Series for the charts and the betas calculated within the period.
     */
    public function history(string $leagueSlug, string $itemSlug, ItemDetailQueryDto $query): ItemHistoryDto;

    /**
     * A page of the metrics of the item by bucket within the period, newest first.
     */
    public function buckets(
        string $leagueSlug,
        string $itemSlug,
        ItemDetailQueryDto $query,
        PaginationDto $pagination,
    ): ItemBucketsPageDto;
}
