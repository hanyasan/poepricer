<?php

namespace App\Services\DataServices\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeItemMetricDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMarketMetricDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMetricsItemDto;
use App\DataTransferObjects\ServiceObjects\MarketDistribution\MarketBreadthDto;
use App\DataTransferObjects\ServiceObjects\MarketDistribution\MarketCategoryDto;
use App\DataTransferObjects\ServiceObjects\MarketDistribution\MarketDistributionDto;
use App\DataTransferObjects\ServiceObjects\MarketDistribution\MarketItemDto;
use App\Enums\CacheTtlEnum;
use App\Enums\Tags\BetasMetricsTagEnum;
use App\Enums\Tags\BucketMetricsTagEnum;
use App\Enums\Tags\ExchangePriceTagEnum;
use App\Enums\Tags\ItemCategoryTagEnum;
use App\Enums\Tags\MetricsItemTagEnum;
use App\Repositories\Poe\Exchange\Metrics\ExchangeMetricsRepositoryContract;
use App\Utility\Metrics\ReturnDistribution;
use App\Utility\Statistics\RobustStatistics;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

final readonly class LeagueMarketService implements LeagueMarketServiceContract
{
    public function __construct(
        private LeagueMetricsLookupServiceContract $lookupService,
        private ExchangeMetricsRepositoryContract $metricsRepository,
        private ReturnDistribution $returnDistribution,
        private RobustStatistics $statistics,
    ) {}

    #[\Override]
    public function distribution(
        string $leagueSlug,
        ?string $currency,
        ?CarbonImmutable $bucket,
        bool $includeThinlyTraded,
    ): MarketDistributionDto {
        $league = $this->lookupService->findLeague($leagueSlug);
        $metricsItem = $this->lookupService->pickMetricsItem($league->id, $currency);

        if ($metricsItem === null) {
            return $this->emptyDistribution(null, []);
        }

        return Cache::tags([
            BucketMetricsTagEnum::bucket_metrics_league_id->name.$league->id,
            BetasMetricsTagEnum::betas_metrics_league_id->name.$league->id,
            ExchangePriceTagEnum::price_league_id->name.$league->id,
            MetricsItemTagEnum::metrics_items_league_id->name.$league->id,
            ItemCategoryTagEnum::item_categories_league_id->name.$league->id,
        ])->remember(
            'marketDistribution:'.$league->id.':'.$metricsItem->exchange_item->trade_id.':'
                .($bucket?->toIso8601String() ?? 'latest').':'.(int) $includeThinlyTraded,
            CacheTtlEnum::HOUR->jiggle(),
            fn (): MarketDistributionDto => $this->buildDistribution($metricsItem, $bucket, $includeThinlyTraded),
        );
    }

    private function buildDistribution(
        ExchangeMetricsItemDto $metricsItem,
        ?CarbonImmutable $bucket,
        bool $includeThinlyTraded,
    ): MarketDistributionDto {
        $currency = $metricsItem->exchange_item->trade_id;
        $buckets = $this->metricsRepository->getMarketBucketStarts(
            $metricsItem->id,
            (int) config('poe.market.buckets_limit'),
        );
        $marketMetric = $this->pickMarketMetric($metricsItem->id, $buckets, $bucket);

        if ($marketMetric === null) {
            return $this->emptyDistribution($currency, $buckets);
        }

        $bucketStart = CarbonImmutable::parse($marketMetric->bucket_start)->utc();
        $items = $this->marketItems($this->metricsRepository->getBucketReturns(
            $metricsItem->id,
            $bucketStart,
            ! $includeThinlyTraded,
        ));
        $returns = array_map(static fn (MarketItemDto $item): float => $item->logReturn, $items);

        return new MarketDistributionDto(
            currency: $currency,
            bucket: $marketMetric->bucket_start,
            buckets: $buckets,
            marketReturn: $marketMetric->market_return,
            median: $returns === [] ? null : $this->statistics->median($returns),
            breadth: $this->returnDistribution->breadth($returns, (float) config('poe.market.flat_threshold')),
            bins: $this->returnDistribution->bins(
                $returns,
                (float) config('poe.market.bin_width'),
                (float) config('poe.market.bin_range'),
            ),
            byCategory: $this->categories($items),
            items: $items,
        );
    }

    /**
     * The market metric of the asked bucket, the latest one when none is asked for.
     *
     * @param  string[]  $buckets  latest bucket starts, newest first
     *
     * @throws ValidationException when the asked bucket does not exist
     */
    private function pickMarketMetric(int $metricsItemId, array $buckets, ?CarbonImmutable $bucket): ?ExchangeMarketMetricDto
    {
        if ($bucket === null) {
            return $buckets === [] ? null : $this->metricsRepository->findMarketMetric(
                $metricsItemId,
                CarbonImmutable::parse($buckets[0])->utc(),
            );
        }

        return $this->metricsRepository->findMarketMetric($metricsItemId, $bucket) ?? throw ValidationException::withMessages([
            'bucket' => 'The league has no such bucket.',
        ]);
    }

    /**
     * Items from the best return to the worst.
     *
     * @param  ExchangeItemMetricDto[]  $metrics
     * @return MarketItemDto[]
     */
    private function marketItems(array $metrics): array
    {
        $items = [];

        foreach ($metrics as $metric) {
            if ($metric->log_return === null) {
                continue;
            }

            $items[] = new MarketItemDto(
                slug: $metric->exchange_item->trade_id,
                name: $metric->exchange_item->name,
                category: $this->categoryOf($metric),
                price: $metric->price,
                logReturn: $metric->log_return,
                units: $metric->units,
                returnWeight: $metric->return_weight,
            );
        }

        usort(
            $items,
            static fn (MarketItemDto $a, MarketItemDto $b): int => [$b->logReturn, $a->slug] <=> [$a->logReturn, $b->slug],
        );

        return $items;
    }

    private function categoryOf(ExchangeItemMetricDto $metric): ?string
    {
        $category = $metric->exchange_item->poe_category;

        return $category === null || $category === '' ? null : $category;
    }

    /**
     * Median return of every category, the best one first, items without a category last among equals.
     *
     * @param  MarketItemDto[]  $items
     * @return MarketCategoryDto[]
     */
    private function categories(array $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            $key = 'category:'.($item->category ?? '');
            $groups[$key] ??= ['category' => $item->category, 'returns' => []];
            $groups[$key]['returns'][] = $item->logReturn;
        }

        $categories = array_values(array_map(
            fn (array $group): MarketCategoryDto => new MarketCategoryDto(
                $group['category'],
                $this->statistics->median($group['returns']),
                count($group['returns']),
            ),
            $groups,
        ));

        usort(
            $categories,
            static fn (MarketCategoryDto $a, MarketCategoryDto $b): int => [$b->median, $a->category === null, $a->category]
                <=> [$a->median, $b->category === null, $b->category],
        );

        return $categories;
    }

    /**
     * @param  string[]  $buckets
     */
    private function emptyDistribution(?string $currency, array $buckets): MarketDistributionDto
    {
        return new MarketDistributionDto(
            currency: $currency,
            bucket: null,
            buckets: $buckets,
            marketReturn: null,
            median: null,
            breadth: new MarketBreadthDto(0, 0, 0),
        );
    }
}
