<?php

namespace App\Services\DataServices\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMetricsItemDto;
use App\Enums\Tags\BetasMetricsTagEnum;
use App\Enums\Tags\BucketMetricsTagEnum;
use App\Repositories\Poe\Exchange\Metrics\ExchangeBucketPriceRepositoryContract;
use App\Repositories\Poe\Exchange\Metrics\ExchangeMetricsItemRepositoryContract;
use App\Repositories\Poe\Exchange\Metrics\ExchangeMetricsRepositoryContract;
use App\Utility\Statistics\RobustStatistics;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class ExchangeMetricsService implements ExchangeMetricsServiceContract
{
    private const string TIMESTAMP_FORMAT = 'Y-m-d H:i:sP';

    public function __construct(
        private ExchangeMetricsItemRepositoryContract $metricsItemRepository,
        private ExchangeBucketPriceRepositoryContract $bucketPriceRepository,
        private ExchangeMetricsRepositoryContract $metricsRepository,
        private RobustStatistics $statistics,
    ) {}

    #[\Override]
    public function collectBucketMetrics(int $leagueId, CarbonImmutable $bucketStart): void
    {
        $bucketStart = $this->alignToBucket($bucketStart);

        Log::info(
            'Starting collect metrics for league_id: '.$leagueId
        );

        foreach ($this->metricsItemRepository->getAllByLeague($leagueId) as $metricsItem) {
            DB::transaction(function () use ($metricsItem, $bucketStart): void {
                $this->collectForMetricsItem($metricsItem, $bucketStart);
            });
        }

        Cache::tags([
            BucketMetricsTagEnum::bucket_metrics_get->name,
            BucketMetricsTagEnum::bucket_metrics_league_id->name.$leagueId,
        ])->flush();

        Log::info(
            'Finished collect metrics for league_id: '.$leagueId
        );
    }

    #[\Override]
    public function recalculateBetas(int $leagueId, CarbonImmutable $asOf): void
    {
        $windowStart = $asOf->subHours($this->bucketHours() * (int) config('poe.metrics.window_buckets'));

        Log::info(
            'Starting recalculate betas for league_id: '.$leagueId
        );

        foreach ($this->metricsItemRepository->getAllByLeague($leagueId) as $metricsItem) {
            $rows = $this->buildBetaRows($metricsItem, $windowStart, $asOf);

            $this->metricsRepository->upsertBetas($rows);
        }

        Cache::tags([
            BetasMetricsTagEnum::betas_metrics_get->name,
            BetasMetricsTagEnum::betas_metrics_league_id->name.$leagueId,
        ])->flush();

        Log::info(
            'Finished recalculate betas for league_id: '.$leagueId
        );
    }

    private function collectForMetricsItem(ExchangeMetricsItemDto $metricsItem, CarbonImmutable $bucketStart): void
    {
        $previousStart = $bucketStart->subHours($this->bucketHours());
        $currencyTradeId = $metricsItem->exchange_item->trade_id;

        $current = $this->summarizeBucket(
            $this->bucketPriceRepository->getPricesByItem(
                $metricsItem->poe_league_id,
                $currencyTradeId,
                $bucketStart,
                $bucketStart->addHours($this->bucketHours()),
            ),
            $metricsItem->exchange_item_id,
        );

        if ($current === []) {
            return;
        }

        $previous = $this->summarizeBucket(
            $this->bucketPriceRepository->getPricesByItem(
                $metricsItem->poe_league_id,
                $currencyTradeId,
                $previousStart,
                $bucketStart,
            ),
            $metricsItem->exchange_item_id,
        );

        $basket = $this->pickBasket(
            $current,
            array_flip($this->metricsRepository->getBasketItemIds($metricsItem->id, $previousStart)),
            $metricsItem->min_price,
        );

        $basket = $this->calculateReturns($basket, $previous);
        $basket = $this->calculateWeights($basket);

        /** @var array<int, array{price: float, units: float|null, volume: float|null, weight: float, return: float}> $reliableReturns */
        $reliableReturns = array_filter(
            $basket,
            static fn (array $item): bool => $item['weight'] > 0.0 && ! is_null($item['return']),
            ARRAY_FILTER_USE_BOTH,
        );
        $marketReturn = empty($reliableReturns) ?
            null :
            $this->statistics->weightedMedian(
                array_map(fn (array $item) => $item['return'], $reliableReturns),
                array_map(fn (array $item) => $item['weight'], $reliableReturns),
            );

        $this->metricsRepository->upsertItemMetrics(
            $this->buildItemMetricRows(
                $metricsItem,
                $bucketStart,
                $basket,
                $marketReturn,
            ),
        );

        if ($marketReturn !== null) {
            $this->metricsRepository->upsertMarketMetric([
                'poe_league_id' => $metricsItem->poe_league_id,
                'exchange_metrics_item_id' => $metricsItem->id,
                'bucket_start' => $bucketStart->format(self::TIMESTAMP_FORMAT),
                'market_return' => $marketReturn,
                'basket_size' => count($reliableReturns),
                'basket_total' => count($basket),
            ]);
        }
    }

    /**
     * An item stays in the basket while it was in the previous one, even when its
     * price fell below the threshold. A new item enters above the threshold or above
     * the volume percentile, so heavily traded cheap items are not lost either.
     *
     * @param  array<int, array{price: float, units: float|null, volume: float|null}>  $currentPrices
     * @param  array<int, int>  $previousBasket  item ids as keys
     * @return array<int, array{price: float, units: float|null, volume: float|null}>
     */
    private function pickBasket(array $currentPrices, array $previousBasket, float $minPrice): array
    {
        $volumes = array_filter(
            array_column($currentPrices, 'volume'),
            static fn (?float $volume): bool => ! is_null($volume),
        );

        // Without a single known volume there is nothing to rank, so only the price threshold applies.
        $percentileVolume = $volumes === [] ? null : $this->statistics->percentile(
            $volumes,
            config('poe.metrics.basket_permission_volume_percentile'),
        );

        return array_filter(
            $currentPrices,
            static fn (array $itemData, int $itemId): bool => isset($previousBasket[$itemId]) ||
                $itemData['price'] >= $minPrice ||
                ($percentileVolume !== null && $itemData['volume'] !== null && $itemData['volume'] >= $percentileVolume),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * A return needs the price of the same item in the previous bucket, otherwise it is absent.
     *
     * @param  array<int, array{price: float, units: float|null, volume: float|null}>  $basketPrices
     * @param  array<int, array{price: float, units: float|null, volume: float|null}>  $previousPrices
     * @return array<int, array{price: float, units: float|null, volume: float|null, return: ?float}>
     */
    private function calculateReturns(array $basketPrices, array $previousPrices): array
    {
        $basketResult = [];

        foreach ($basketPrices as $itemId => $itemData) {
            $return = isset($previousPrices[$itemId]['price']) ? log($itemData['price'] / $previousPrices[$itemId]['price']) : null;

            $basketResult[$itemId] = [
                ...$itemData,
                'return' => $return,
            ];
        }

        return $basketResult;
    }

    /**
     * @param  array<int, array{price: float, units: float|null, volume: float|null, weight: float, return: ?float}>  $basket
     * @return array<int, array{
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
     * }>
     */
    private function buildItemMetricRows(
        ExchangeMetricsItemDto $metricsItem,
        CarbonImmutable $bucketStart,
        array $basket,
        ?float $marketReturn,
    ): array {
        $betas = $this->metricsRepository->getLatestBetas($metricsItem->id, $bucketStart);
        $history = $this->metricsRepository->getResidualHistory(
            $metricsItem->id,
            $bucketStart->subHours($this->bucketHours() * ((int) config('poe.metrics.window_buckets') - 1)),
            $bucketStart,
        );
        $minObservations = (int) config('poe.metrics.min_residual_observations');
        $rows = [];

        foreach ($basket as $itemId => $itemData) {
            $logReturn = $itemData['return'];
            $residual = ($logReturn !== null && $marketReturn !== null && $itemData['weight'] > 0.0)
                ? $logReturn - ($betas[$itemId] ?? 1.0) * $marketReturn
                : null;

            $window = $history[$itemId] ?? [];

            if ($residual !== null) {
                $window[] = $residual;
            }

            $rows[] = [
                'exchange_item_id' => $itemId,
                'exchange_metrics_item_id' => $metricsItem->id,
                'bucket_start' => $bucketStart->format(self::TIMESTAMP_FORMAT),
                'price' => $itemData['price'],
                'log_return' => $logReturn,
                'residual' => $residual,
                'residual_mad' => count($window) >= $minObservations ? $this->statistics->scaledMad($window) : null,
                'observations' => count($window),
                'units' => $itemData['units'],
                'return_weight' => $itemData['weight'],
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{
     *     exchange_item_id: int,
     *     exchange_metrics_item_id: int,
     *     calculated_at: string,
     *     beta: float,
     *     raw_beta: float,
     *     r_squared: float|null,
     *     observations: int,
     * }>
     */
    private function buildBetaRows(
        ExchangeMetricsItemDto $metricsItem,
        CarbonImmutable $windowStart,
        CarbonImmutable $asOf,
    ): array {
        $minObservations = (int) config('poe.metrics.min_beta_observations');
        $priorWeight = (int) config('poe.metrics.beta_prior_weight');
        $rows = [];

        $pairsByItem = $this->metricsRepository->getReturnPairs($metricsItem->id, $windowStart, $asOf);

        foreach ($pairsByItem as $itemId => $pairs) {
            $observations = count($pairs);

            if ($observations < $minObservations) {
                continue;
            }

            $market = array_column($pairs, 'market');
            $item = array_column($pairs, 'item');
            $rawBeta = $this->statistics->theilSenSlope($market, $item);

            if ($rawBeta === null) {
                continue;
            }

            $weight = (float) ($observations / ($observations + $priorWeight));

            $rows[] = [
                'exchange_item_id' => $itemId,
                'exchange_metrics_item_id' => $metricsItem->id,
                'calculated_at' => $asOf->format(self::TIMESTAMP_FORMAT),
                'beta' => $weight * $rawBeta + (1.0 - $weight),
                'raw_beta' => $rawBeta,
                'r_squared' => $this->statistics->squaredCorrelation($market, $item),
                'observations' => $observations,
            ];
        }

        return $rows;
    }

    /**
     * Median price and mean hourly units of every item in the bucket. The units are
     * averaged, not summed, because every snapshot already reports a full hour of trades.
     * The units are absent when none of the snapshots knows the volume.
     *
     * @param  array<int, list<array{price: float, units: float|null, volume: float|null}>>  $samplesByItem
     * @return array<int, array{price: float, units: float|null, volume: float|null}>
     *
     * @psalm-suppress InvalidReturnStatement,InvalidReturnType
     */
    private function summarizeBucket(array $samplesByItem, int $excludedItemId): array
    {
        $summary = [];

        foreach ($samplesByItem as $itemId => $samples) {
            if ($itemId === $excludedItemId) {
                continue;
            }

            $summary[$itemId]['price'] = $this->statistics->median(array_column($samples, 'price'));

            /** @var array<int, list<array{price: float, units: float, volume: float}>> $knownUnitsSamples */
            $knownUnitsSamples = array_filter($samples, static fn (array $sample): bool => ! is_null($sample['units']) && ! is_null($sample['volume']));

            $summary[$itemId]['units'] = empty($knownUnitsSamples) ? null :
                (float) array_sum(array_column($knownUnitsSamples, 'units')) / (float) count($knownUnitsSamples);
            $summary[$itemId]['volume'] = empty($knownUnitsSamples) ? null :
                $this->statistics->median(array_column($knownUnitsSamples, 'volume'));
        }

        return $summary;
    }

    /**
     * @param  array<int, array{price: float, units?: float}>  $summary
     * @return array<int, float>
     */
    private function pluckByItem(array $summary, string $field): array
    {
        $values = [];

        foreach ($summary as $itemId => $fields) {
            if (isset($fields[$field])) {
                $values[$itemId] = $fields[$field];
            }
        }

        return $values;
    }

    /**
     * @param  array<int, array{price: float, units: float|null, volume: float|null, return: ?float}>  $basket
     * @return array<int, array{price: float, units: float|null, volume: float|null, weight: float, return: ?float}>
     */
    private function calculateWeights(array $basket): array
    {
        $basketResult = [];

        foreach ($basket as $key => $item) {
            $basketResult[$key] = [
                ...$item,
                'weight' => $this->unitsWeight($item['units']),
            ];
        }

        return $basketResult;
    }

    /**
     * Zero below the minimum, growing linearly up to one at the full weight level.
     * Unknown units (rows saved before the volume was stored) are fully trusted.
     */
    private function unitsWeight(?float $units): float
    {
        if ($units === null) {
            return 1.0;
        }

        if ($units < (float) config('poe.metrics.min_hourly_units')) {
            return 0.0;
        }

        return min(1.0, $units / (float) config('poe.metrics.full_weight_hourly_units'));
    }

    private function alignToBucket(CarbonImmutable $moment): CarbonImmutable
    {
        $moment = $moment->utc()->startOfHour();

        return $moment->subHours($moment->hour % $this->bucketHours());
    }

    private function bucketHours(): int
    {
        return (int) config('poe.metrics.bucket_hours');
    }
}
