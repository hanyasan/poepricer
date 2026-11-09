<?php

namespace App\Services\DataServices\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\ExchangeItemDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMetricsItemDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemMetricRowDto;
use App\DataTransferObjects\ServiceObjects\ItemDetail\ItemBucketsPageDto;
use App\DataTransferObjects\ServiceObjects\ItemDetail\ItemCurrentDto;
use App\DataTransferObjects\ServiceObjects\ItemDetail\ItemDetailQueryDto;
use App\DataTransferObjects\ServiceObjects\ItemDetail\ItemHistoryDto;
use App\DataTransferObjects\ServiceObjects\ItemDetail\ItemInfoDto;
use App\DataTransferObjects\ServiceObjects\ItemDetail\ItemSummaryDto;
use App\DataTransferObjects\ServiceObjects\PaginationDto;
use App\Enums\CacheTtlEnum;
use App\Enums\MetricPeriodEnum;
use App\Enums\Tags\BetasMetricsTagEnum;
use App\Enums\Tags\BucketMetricsTagEnum;
use App\Enums\Tags\ItemCategoryTagEnum;
use App\Enums\Tags\MetricsItemTagEnum;
use App\Exceptions\Item\ExchangeItemNotFoundException;
use App\Repositories\Poe\Exchange\ExchangeItem\ExchangeItemRepositoryContract;
use App\Repositories\Poe\Exchange\Metrics\ExchangeBucketPriceRepositoryContract;
use App\Repositories\Poe\Exchange\Metrics\ExchangeItemDetailRepositoryContract;
use App\Repositories\Poe\Exchange\Metrics\ExchangeMetricsRepositoryContract;
use App\Utility\Metrics\ItemMetricFlags;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final readonly class LeagueItemDetailService implements LeagueItemDetailServiceContract
{
    public function __construct(
        private LeagueMetricsLookupServiceContract $lookupService,
        private ExchangeMetricsRepositoryContract $metricsRepository,
        private ExchangeItemRepositoryContract $itemRepository,
        private ExchangeItemDetailRepositoryContract $detailRepository,
        private ExchangeBucketPriceRepositoryContract $bucketPriceRepository,
        private ItemMetricFlags $flags,
    ) {}

    #[\Override]
    public function summary(string $leagueSlug, string $itemSlug, ?string $currency): ItemSummaryDto
    {
        $league = $this->lookupService->findLeague($leagueSlug);
        $metricsItem = $this->lookupService->pickMetricsItem($league->id, $currency);

        if ($metricsItem === null) {
            return new ItemSummaryDto($this->info($this->findItem($league->id, $itemSlug)), null, null, null, null);
        }

        /** @var ItemSummaryDto $summary */
        $summary = Cache::tags([
            BucketMetricsTagEnum::bucket_metrics_league_id->name.$league->id,
            BetasMetricsTagEnum::betas_metrics_league_id->name.$league->id,
            MetricsItemTagEnum::metrics_items_league_id->name.$league->id,
            ItemCategoryTagEnum::item_categories_league_id->name.$league->id,
        ])->remember(
            'leagueItemSummary:'.$league->id.':'.$metricsItem->exchange_item->trade_id.':'.$itemSlug,
            CacheTtlEnum::HOUR->jiggle(),
            fn (): ItemSummaryDto => $this->buildSummary($league->id, $metricsItem, $itemSlug),
        );

        // The freshest price is not cached: it changes with every price check, not every hour.
        $item = $this->findItem($league->id, $itemSlug);

        return $this->withCurrentPrice($summary, $league->id, $item);
    }

    #[\Override]
    public function history(string $leagueSlug, string $itemSlug, ItemDetailQueryDto $query): ItemHistoryDto
    {
        $league = $this->lookupService->findLeague($leagueSlug);
        $metricsItem = $this->lookupService->pickMetricsItem($league->id, $query->currency);
        $item = $this->findItem($league->id, $itemSlug);

        if ($metricsItem === null) {
            return new ItemHistoryDto(null, $query->period, null);
        }

        $currency = $metricsItem->exchange_item->trade_id;
        $latest = $this->metricsRepository->findLatestMarketMetric($metricsItem->id);

        if ($latest === null) {
            return new ItemHistoryDto($currency, $query->period, null);
        }

        $after = $this->windowStart(CarbonImmutable::parse($latest->bucket_start), $query->period);

        return new ItemHistoryDto(
            currency: $currency,
            period: $query->period,
            lastBucket: $latest->bucket_start,
            series: $this->detailRepository->getSeries($metricsItem->id, $item->id, $after),
            betaHistory: $this->detailRepository->getBetaHistory($metricsItem->id, $item->id, $after),
        );
    }

    #[\Override]
    public function buckets(
        string $leagueSlug,
        string $itemSlug,
        ItemDetailQueryDto $query,
        PaginationDto $pagination,
    ): ItemBucketsPageDto {
        $league = $this->lookupService->findLeague($leagueSlug);
        $metricsItem = $this->lookupService->pickMetricsItem($league->id, $query->currency);
        $item = $this->findItem($league->id, $itemSlug);

        if ($metricsItem === null) {
            return $this->emptyBuckets($query, $pagination, null);
        }

        $currency = $metricsItem->exchange_item->trade_id;
        $latest = $this->metricsRepository->findLatestMarketMetric($metricsItem->id);

        if ($latest === null) {
            return $this->emptyBuckets($query, $pagination, $currency);
        }

        $page = $this->detailRepository->paginateMetrics(
            $metricsItem->id,
            $item->id,
            $this->windowStart(CarbonImmutable::parse($latest->bucket_start), $query->period),
            $pagination,
        );

        return new ItemBucketsPageDto(
            items: $page->items,
            total: $page->total,
            currentPage: $pagination->page,
            lastPage: max(1, (int) ceil($page->total / $pagination->perPage)),
            perPage: $pagination->perPage,
            currency: $currency,
            period: $query->period,
        );
    }

    private function buildSummary(int $leagueId, ExchangeMetricsItemDto $metricsItem, string $itemSlug): ItemSummaryDto
    {
        $item = $this->findItem($leagueId, $itemSlug);
        $latest = $this->metricsRepository->findLatestMarketMetric($metricsItem->id);
        $metric = $latest === null
            ? null
            : $this->detailRepository->findMetric(
                $metricsItem->id,
                $item->id,
                CarbonImmutable::parse($latest->bucket_start),
            );

        return new ItemSummaryDto(
            item: $this->info($item),
            currency: $metricsItem->exchange_item->trade_id,
            lastBucket: $latest?->bucket_start,
            current: $metric === null ? null : $this->current($metric),
            beta: $this->detailRepository->findLatestBeta($metricsItem->id, $item->id),
        );
    }

    private function withCurrentPrice(ItemSummaryDto $summary, int $leagueId, ExchangeItemDto $item): ItemSummaryDto
    {
        if ($summary->currency === null) {
            return $summary;
        }

        $latestPrices = $this->bucketPriceRepository->getLatestPrices($leagueId, $summary->currency);

        return new ItemSummaryDto(
            item: $summary->item,
            currency: $summary->currency,
            lastBucket: $summary->lastBucket,
            current: $summary->current,
            beta: $summary->beta,
            currentPrice: $latestPrices['prices'][$item->id] ?? null,
            currentPriceAt: $latestPrices['snapshot_at'],
        );
    }

    private function findItem(int $leagueId, string $itemSlug): ExchangeItemDto
    {
        return $this->itemRepository->findByTradeId($leagueId, $itemSlug) ?? throw new ExchangeItemNotFoundException;
    }

    private function info(ExchangeItemDto $item): ItemInfoDto
    {
        return new ItemInfoDto(
            $item->trade_id,
            $item->name,
            $item->poe_category === null || $item->poe_category === '' ? null : $item->poe_category,
        );
    }

    private function current(ItemMetricRowDto $metric): ItemCurrentDto
    {
        return new ItemCurrentDto(
            price: $metric->price,
            logReturn: $metric->log_return,
            residual: $metric->residual,
            residualMad: $metric->residual_mad,
            observations: $metric->observations,
            units: $metric->units,
            returnWeight: $metric->return_weight,
            lowData: $this->flags->isLowData($metric->observations),
            isUnusual: $this->flags->isUnusual($metric->residual, $metric->residual_mad),
        );
    }

    /**
     * Start of the period counted back from the last bucket, null for the whole league.
     */
    private function windowStart(CarbonImmutable $lastBucket, MetricPeriodEnum $period): ?CarbonImmutable
    {
        $hours = $period->hours();

        return $hours === null ? null : $lastBucket->utc()->subHours($hours);
    }

    private function emptyBuckets(ItemDetailQueryDto $query, PaginationDto $pagination, ?string $currency): ItemBucketsPageDto
    {
        return new ItemBucketsPageDto(
            items: [],
            total: 0,
            currentPage: $pagination->page,
            lastPage: 1,
            perPage: $pagination->perPage,
            currency: $currency,
            period: $query->period,
        );
    }
}
