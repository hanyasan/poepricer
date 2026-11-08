<?php

namespace App\Services\DataServices\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeItemMetricDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMarketMetricDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMetricsItemDto;
use App\DataTransferObjects\RepositoryObjects\League\PoeLeagueDto;
use App\DataTransferObjects\ServiceObjects\LeagueOverview\LeagueCurrencyDto;
use App\DataTransferObjects\ServiceObjects\LeagueOverview\LeagueItemMoveDto;
use App\DataTransferObjects\ServiceObjects\LeagueOverview\LeagueOverviewDto;
use App\DataTransferObjects\ServiceObjects\LeagueOverview\LeagueUnusualMoveDto;
use App\Enums\CacheTtlEnum;
use App\Enums\MetricPeriodEnum;
use App\Enums\Tags\BetasMetricsTagEnum;
use App\Enums\Tags\BucketMetricsTagEnum;
use App\Enums\Tags\ExchangePriceTagEnum;
use App\Enums\Tags\MetricsItemTagEnum;
use App\Enums\Tags\PoeLeagueTagEnum;
use App\Exceptions\League\LeagueNotFoundException;
use App\Repositories\Poe\Exchange\Metrics\ExchangeMetricsItemRepositoryContract;
use App\Repositories\Poe\Exchange\Metrics\ExchangeMetricsRepositoryContract;
use App\Repositories\Poe\Exchange\PriceCheck\PriceCheckRepositoryContract;
use App\Repositories\Poe\PoeLeagueRepositoryContract;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

final readonly class LeagueOverviewService implements LeagueOverviewServiceContract
{
    private const int WEEK_HOURS = 24 * 7;

    private const int DAY_HOURS = 24;

    public function __construct(
        private PoeLeagueRepositoryContract $leagueRepository,
        private ExchangeMetricsItemRepositoryContract $metricsItemRepository,
        private ExchangeMetricsRepositoryContract $metricsRepository,
        private PriceCheckRepositoryContract $priceCheckRepository,
    ) {}

    /**
     * @return LeagueCurrencyDto[]
     */
    #[\Override]
    public function currencies(string $leagueSlug): array
    {
        $league = $this->findLeague($leagueSlug);

        return array_map(
            static fn (ExchangeMetricsItemDto $metricsItem): LeagueCurrencyDto => new LeagueCurrencyDto(
                $metricsItem->exchange_item->trade_id,
                $metricsItem->exchange_item->name,
            ),
            $this->metricsItems($league->id),
        );
    }

    #[\Override]
    public function overview(string $leagueSlug, ?string $currency, MetricPeriodEnum $period): LeagueOverviewDto
    {
        $league = $this->findLeague($leagueSlug);
        $metricsItems = $this->metricsItems($league->id);
        $metricsItem = $this->pickMetricsItem($metricsItems, $currency);

        if ($metricsItem === null) {
            return $this->emptyOverview($league->id, null, $period);
        }

        $tradeId = $metricsItem->exchange_item->trade_id;

        return Cache::tags([
            BucketMetricsTagEnum::bucket_metrics_league_id->name.$league->id,
            BetasMetricsTagEnum::betas_metrics_league_id->name.$league->id,
            ExchangePriceTagEnum::price_league_id->name.$league->id,
            MetricsItemTagEnum::metrics_items_league_id->name.$league->id,
        ])->remember(
            'leagueOverview:'.$league->id.':'.$tradeId.':'.$period->value,
            CacheTtlEnum::HOUR->jiggle(),
            fn (): LeagueOverviewDto => $this->buildOverview($league->id, $metricsItem, $period),
        );
    }

    private function findLeague(string $slug): PoeLeagueDto
    {
        return Cache::tags([
            PoeLeagueTagEnum::league->name,
            PoeLeagueTagEnum::league_get->name,
        ])->remember(
            'leagueBySlug:'.$slug,
            CacheTtlEnum::HOUR->jiggle(),
            fn (): PoeLeagueDto => $this->leagueRepository->findActiveBySlug($slug) ?? throw new LeagueNotFoundException,
        );
    }

    /**
     * Metrics items of the league with the default main currency first.
     *
     * @return ExchangeMetricsItemDto[]
     */
    private function metricsItems(int $leagueId): array
    {
        return Cache::tags([
            MetricsItemTagEnum::metrics_items_league_id->name.$leagueId,
        ])->remember(
            'leagueMetricsItems:'.$leagueId,
            CacheTtlEnum::HOUR->jiggle(),
            function () use ($leagueId): array {
                $defaultTradeId = (string) config('poe.base.default_main_trade_id');
                $metricsItems = $this->metricsItemRepository->getAllByLeague($leagueId);

                usort(
                    $metricsItems,
                    static fn (ExchangeMetricsItemDto $a, ExchangeMetricsItemDto $b): int => (int) ($b->exchange_item->trade_id === $defaultTradeId)
                        <=> (int) ($a->exchange_item->trade_id === $defaultTradeId),
                );

                return $metricsItems;
            },
        );
    }

    /**
     * @param  ExchangeMetricsItemDto[]  $metricsItems
     *
     * @throws ValidationException when the currency is not a metric currency of the league
     */
    private function pickMetricsItem(array $metricsItems, ?string $currency): ?ExchangeMetricsItemDto
    {
        if ($currency === null) {
            return $metricsItems[0] ?? null;
        }

        foreach ($metricsItems as $metricsItem) {
            if ($metricsItem->exchange_item->trade_id === $currency) {
                return $metricsItem;
            }
        }

        throw ValidationException::withMessages([
            'currency' => 'The league has no such metric currency.',
        ]);
    }

    private function buildOverview(int $leagueId, ExchangeMetricsItemDto $metricsItem, MetricPeriodEnum $period): LeagueOverviewDto
    {
        $currency = $metricsItem->exchange_item->trade_id;
        $latest = $this->metricsRepository->findLatestMarketMetric($metricsItem->id);

        if ($latest === null) {
            return $this->emptyOverview($leagueId, $currency, $period);
        }

        $bucket = CarbonImmutable::parse($latest->bucket_start)->utc();
        $weekStart = $bucket->subHours(self::WEEK_HOURS);

        $series = $this->metricsRepository->getMarketSeries(
            $metricsItem->id,
            $period === MetricPeriodEnum::League ? null : $weekStart,
        );

        $limit = (int) config('poe.overview.top_limit');
        $unusual = $this->metricsRepository->getUnusualMoves(
            $metricsItem->id,
            $bucket,
            (float) config('poe.overview.unusual_residual_multiplier'),
            (int) config('poe.overview.unusual_limit'),
        );

        return new LeagueOverviewDto(
            currency: $currency,
            period: $period,
            lastBucket: $latest->bucket_start,
            marketReturn12h: $latest->market_return,
            marketReturn7d: $this->sumReturns($this->after($series, $weekStart)),
            basketSize: $latest->basket_size,
            lastSnapshotAt: $this->priceCheckRepository->findLatestCreatedAtByLeague($leagueId),
            marketReturnSeries: $period === MetricPeriodEnum::Day
                ? $this->after($series, $bucket->subHours(self::DAY_HOURS))
                : $series,
            unusualMoves: $this->unusualMoves($metricsItem->id, $bucket, $unusual),
            topGainers: $this->itemMoves($this->metricsRepository->getTopGainers($metricsItem->id, $bucket, $limit)),
            topLosers: $this->itemMoves($this->metricsRepository->getTopLosers($metricsItem->id, $bucket, $limit)),
        );
    }

    private function emptyOverview(int $leagueId, ?string $currency, MetricPeriodEnum $period): LeagueOverviewDto
    {
        return new LeagueOverviewDto(
            currency: $currency,
            period: $period,
            lastBucket: null,
            marketReturn12h: null,
            marketReturn7d: null,
            basketSize: 0,
            lastSnapshotAt: $this->priceCheckRepository->findLatestCreatedAtByLeague($leagueId),
        );
    }

    /**
     * @param  ExchangeMarketMetricDto[]  $series
     * @return ExchangeMarketMetricDto[]
     */
    private function after(array $series, CarbonImmutable $moment): array
    {
        return array_values(array_filter(
            $series,
            static fn (ExchangeMarketMetricDto $metric): bool => CarbonImmutable::parse($metric->bucket_start)->greaterThan($moment),
        ));
    }

    /**
     * Log returns add up over time.
     *
     * @param  ExchangeMarketMetricDto[]  $series
     */
    private function sumReturns(array $series): ?float
    {
        if ($series === []) {
            return null;
        }

        return array_sum(array_map(
            static fn (ExchangeMarketMetricDto $metric): float => $metric->market_return,
            $series,
        ));
    }

    /**
     * @param  ExchangeItemMetricDto[]  $metrics
     * @return LeagueItemMoveDto[]
     */
    private function itemMoves(array $metrics): array
    {
        $moves = [];

        foreach ($metrics as $metric) {
            if ($metric->log_return === null) {
                continue;
            }

            $moves[] = new LeagueItemMoveDto(
                $metric->exchange_item->trade_id,
                $metric->exchange_item->name,
                $metric->price,
                $metric->log_return,
            );
        }

        return $moves;
    }

    /**
     * @param  ExchangeItemMetricDto[]  $metrics
     * @return LeagueUnusualMoveDto[]
     */
    private function unusualMoves(int $metricsItemId, CarbonImmutable $bucket, array $metrics): array
    {
        $betas = $this->metricsRepository->getLatestBetas(
            $metricsItemId,
            $bucket,
            array_map(static fn (ExchangeItemMetricDto $metric): int => $metric->exchange_item_id, $metrics),
        );
        $moves = [];

        foreach ($metrics as $metric) {
            if ($metric->log_return === null || $metric->residual === null || $metric->residual_mad === null) {
                continue;
            }

            $moves[] = new LeagueUnusualMoveDto(
                slug: $metric->exchange_item->trade_id,
                name: $metric->exchange_item->name,
                price: $metric->price,
                logReturn: $metric->log_return,
                residual: $metric->residual,
                residualMad: $metric->residual_mad,
                ratio: $metric->residual / $metric->residual_mad,
                beta: $betas[$metric->exchange_item_id] ?? 1.0,
                observations: $metric->observations,
            );
        }

        return $moves;
    }
}
