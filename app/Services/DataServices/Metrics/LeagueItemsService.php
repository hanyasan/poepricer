<?php

namespace App\Services\DataServices\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMetricsItemDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemPricePointDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemTableRowDto;
use App\DataTransferObjects\ServiceObjects\LeagueItems\ItemsFilterDto;
use App\DataTransferObjects\ServiceObjects\LeagueItems\ItemsSortDto;
use App\DataTransferObjects\ServiceObjects\LeagueItems\LeagueItemCategoriesDto;
use App\DataTransferObjects\ServiceObjects\LeagueItems\LeagueItemRowDto;
use App\DataTransferObjects\ServiceObjects\LeagueItems\LeagueItemsPageDto;
use App\DataTransferObjects\ServiceObjects\PaginatedMetaDto;
use App\DataTransferObjects\ServiceObjects\PaginationDto;
use App\Enums\CacheTtlEnum;
use App\Enums\ItemsSortFieldEnum;
use App\Enums\SortDirectionEnum;
use App\Enums\Tags\BetasMetricsTagEnum;
use App\Enums\Tags\BucketMetricsTagEnum;
use App\Enums\Tags\ExchangePriceTagEnum;
use App\Enums\Tags\ItemCategoryTagEnum;
use App\Enums\Tags\MetricsItemTagEnum;
use App\Repositories\Poe\Exchange\ExchangeItem\ExchangeItemRepositoryContract;
use App\Repositories\Poe\Exchange\Metrics\ExchangeItemTableRepositoryContract;
use App\Repositories\Poe\Exchange\Metrics\ExchangeMetricsRepositoryContract;
use App\Repositories\Poe\Exchange\PriceCheck\PriceCheckRepositoryContract;
use App\Utility\Metrics\ItemMetricFlags;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final readonly class LeagueItemsService implements LeagueItemsServiceContract
{
    public function __construct(
        private LeagueMetricsLookupServiceContract $lookupService,
        private ExchangeMetricsRepositoryContract $metricsRepository,
        private ExchangeItemRepositoryContract $itemRepository,
        private ExchangeItemTableRepositoryContract $itemTableRepository,
        private PriceCheckRepositoryContract $priceCheckRepository,
        private ItemMetricFlags $flags,
    ) {}

    #[\Override]
    public function categories(string $leagueSlug): LeagueItemCategoriesDto
    {
        $leagueId = $this->lookupService->findLeague($leagueSlug)->id;

        return Cache::tags([
            ItemCategoryTagEnum::item_categories_league_id->name.$leagueId,
        ])->remember(
            'leagueItemCategories:'.$leagueId,
            CacheTtlEnum::HOUR->jiggle(),
            fn (): LeagueItemCategoriesDto => new LeagueItemCategoriesDto(
                $this->itemRepository->getCategoriesByLeague($leagueId),
                $this->itemRepository->hasUncategorizedByLeague($leagueId),
            ),
        );
    }

    #[\Override]
    public function items(
        string $leagueSlug,
        ItemsFilterDto $filter,
        ItemsSortDto $sort,
        PaginationDto $pagination,
    ): LeagueItemsPageDto {
        $league = $this->lookupService->findLeague($leagueSlug);
        $metricsItem = $this->lookupService->pickMetricsItem($league->id, $filter->currency);

        if ($metricsItem === null) {
            return $this->emptyPage($pagination, null);
        }

        if (! $this->isDefaultQuery($filter, $sort, $pagination)) {
            return $this->buildPage($league->id, $metricsItem, $filter, $sort, $pagination);
        }

        return Cache::tags([
            BucketMetricsTagEnum::bucket_metrics_league_id->name.$league->id,
            BetasMetricsTagEnum::betas_metrics_league_id->name.$league->id,
            ExchangePriceTagEnum::price_get->name,
            ExchangePriceTagEnum::price_league_id->name.$league->id,
            MetricsItemTagEnum::metrics_items_league_id->name.$league->id,
        ])->remember(
            'leagueItemsPage:'.$league->id.':'.$metricsItem->exchange_item->trade_id,
            CacheTtlEnum::HOUR->jiggle(),
            fn (): LeagueItemsPageDto => $this->buildPage($league->id, $metricsItem, $filter, $sort, $pagination),
        );
    }

    /**
     * The page the frontend opens the table with: the default main currency, the
     * default sort and the first page. Only it is cached, every other combination
     * of the query parameters is built on every request.
     */
    private function isDefaultQuery(ItemsFilterDto $filter, ItemsSortDto $sort, PaginationDto $pagination): bool
    {
        return in_array(
            $filter->currency,
            [null, (string) config('poe.base.default_main_trade_id')],
            true,
        )
            && $filter->search === null
            && $filter->category === null
            && ! $filter->uncategorized
            && $sort->field === ItemsSortFieldEnum::Units
            && $sort->direction === SortDirectionEnum::Desc
            && $pagination->page === 1
            && $pagination->perPage === (int) config('poe.items.per_page');
    }

    private function buildPage(
        int $leagueId,
        ExchangeMetricsItemDto $metricsItem,
        ItemsFilterDto $filter,
        ItemsSortDto $sort,
        PaginationDto $pagination,
    ): LeagueItemsPageDto {
        $currency = $metricsItem->exchange_item->trade_id;
        $latest = $this->metricsRepository->findLatestMarketMetric($metricsItem->id);

        if ($latest === null) {
            return $this->emptyPage($pagination, $currency);
        }

        $bucket = CarbonImmutable::parse($latest->bucket_start)->utc();
        $page = $this->itemTableRepository->paginate(
            $leagueId,
            $metricsItem->id,
            $currency,
            $bucket,
            $filter,
            $sort,
            $pagination,
        );

        $sparklines = $this->itemTableRepository->getPriceSeries(
            $metricsItem->id,
            array_map(static fn (ItemTableRowDto $row): int => $row->id, $page->items),
            $bucket->subDays((int) config('poe.base.sparkline_days')),
        );

        return new LeagueItemsPageDto(
            items: array_map(
                fn (ItemTableRowDto $row): LeagueItemRowDto => $this->row($row, $sparklines[$row->id] ?? []),
                $page->items,
            ),
            meta: new PaginatedMetaDto(
                $page->total,
                $pagination->page,
                $this->lastPage($page->total, $pagination),
                $pagination->perPage,
            ),
            currency: $currency,
            lastBucket: $latest->bucket_start,
            lastSnapshotAt: $this->priceCheckRepository->findLatestCreatedAtByLeague($leagueId),
        );
    }

    /**
     * @param  ItemPricePointDto[]  $sparkline
     */
    private function row(ItemTableRowDto $row, array $sparkline): LeagueItemRowDto
    {
        $observations = $row->observations ?? 0;

        return new LeagueItemRowDto(
            slug: $row->trade_id,
            name: $row->name,
            category: $row->poe_category === '' ? null : $row->poe_category,
            price: $row->current_price ?? $row->price,
            logReturn: $row->log_return,
            residual: $row->residual,
            residualMad: $row->residual_mad,
            beta: $row->beta,
            observations: $observations,
            units: $row->units,
            returnWeight: $row->return_weight,
            lowData: $this->flags->isLowData($observations),
            isUnusual: $this->flags->isUnusual($row->residual, $row->residual_mad),
            sparkline: $sparkline,
        );
    }

    private function lastPage(int $total, PaginationDto $pagination): int
    {
        return max(1, (int) ceil($total / $pagination->perPage));
    }

    private function emptyPage(PaginationDto $pagination, ?string $currency): LeagueItemsPageDto
    {
        return new LeagueItemsPageDto(
            items: [],
            meta: new PaginatedMetaDto(
                0,
                $pagination->page,
                1,
                $pagination->perPage,
            ),
            currency: $currency,
            lastBucket: null,
        );
    }
}
