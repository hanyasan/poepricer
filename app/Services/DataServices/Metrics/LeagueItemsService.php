<?php

namespace App\Services\DataServices\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemPricePointDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemTableRowDto;
use App\DataTransferObjects\ServiceObjects\LeagueItems\ItemsFilterDto;
use App\DataTransferObjects\ServiceObjects\LeagueItems\ItemsSortDto;
use App\DataTransferObjects\ServiceObjects\LeagueItems\LeagueItemCategoriesDto;
use App\DataTransferObjects\ServiceObjects\LeagueItems\LeagueItemRowDto;
use App\DataTransferObjects\ServiceObjects\LeagueItems\LeagueItemsPageDto;
use App\DataTransferObjects\ServiceObjects\PaginationDto;
use App\Enums\CacheTtlEnum;
use App\Enums\Tags\ItemCategoryTagEnum;
use App\Repositories\Poe\Exchange\ExchangeItem\ExchangeItemRepositoryContract;
use App\Repositories\Poe\Exchange\Metrics\ExchangeItemTableRepositoryContract;
use App\Repositories\Poe\Exchange\Metrics\ExchangeMetricsRepositoryContract;
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

        $currency = $metricsItem->exchange_item->trade_id;
        $latest = $this->metricsRepository->findLatestMarketMetric($metricsItem->id);

        if ($latest === null) {
            return $this->emptyPage($pagination, $currency);
        }

        $bucket = CarbonImmutable::parse($latest->bucket_start)->utc();
        $page = $this->itemTableRepository->paginate(
            $league->id,
            $metricsItem->id,
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
            total: $page->total,
            currentPage: $pagination->page,
            lastPage: $this->lastPage($page->total, $pagination),
            perPage: $pagination->perPage,
            currency: $currency,
            lastBucket: $latest->bucket_start,
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
            price: $row->price,
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
            total: 0,
            currentPage: $pagination->page,
            lastPage: 1,
            perPage: $pagination->perPage,
            currency: $currency,
            lastBucket: null,
        );
    }
}
