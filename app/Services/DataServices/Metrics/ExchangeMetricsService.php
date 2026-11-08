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

        $basketPrices = $this->pickBasket(
            $this->pluckByItem($current, 'price'),
            array_flip($this->metricsRepository->getBasketItemIds($metricsItem->id, $previousStart)),
            $metricsItem->min_price,
        );

        $returns = $this->calculateReturns($basketPrices, $this->pluckByItem($previous, 'price'));
        $weights = $this->returnWeights($returns, $this->pluckByItem($current, 'units'), $this->pluckByItem($previous, 'units'));
        $reliableReturns = array_filter(
            $returns,
            static fn (float $return, int $itemId): bool => $weights[$itemId] > 0.0,
            ARRAY_FILTER_USE_BOTH,
        );
        $marketReturn = $reliableReturns === []
            ? null
            : $this->statistics->weightedMedian($reliableReturns, $weights);

        $this->metricsRepository->upsertItemMetrics(
            $this->buildItemMetricRows(
                $metricsItem,
                $bucketStart,
                $basketPrices,
                $returns,
                $marketReturn,
                $this->pluckByItem($current, 'units'),
                $weights,
            ),
        );

        if ($marketReturn !== null) {
            $this->metricsRepository->upsertMarketMetric([
                'poe_league_id' => $metricsItem->poe_league_id,
                'exchange_metrics_item_id' => $metricsItem->id,
                'bucket_start' => $bucketStart->format(self::TIMESTAMP_FORMAT),
                'market_return' => $marketReturn,
                'basket_size' => count($reliableReturns),
                'basket_total' => count($basketPrices),
            ]);
        }
    }

    /**
     * An item stays in the basket while it was in the previous one, even when its
     * price fell below the threshold. A new item enters only above the threshold.
     *
     * @param  array<int, float>  $currentPrices
     * @param  array<int, int>  $previousBasket  item ids as keys
     * @return array<int, float>
     */
    private function pickBasket(array $currentPrices, array $previousBasket, float $minPrice): array
    {
        return array_filter(
            $currentPrices,
            static fn (float $price, int $itemId): bool => isset($previousBasket[$itemId]) || $price >= $minPrice,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @param  array<int, float>  $basketPrices
     * @param  array<int, float>  $previousPrices
     * @return array<int, float>
     */
    private function calculateReturns(array $basketPrices, array $previousPrices): array
    {
        $returns = [];

        foreach ($basketPrices as $itemId => $price) {
            if (isset($previousPrices[$itemId])) {
                $returns[$itemId] = log($price / $previousPrices[$itemId]);
            }
        }

        return $returns;
    }

    /**
     * @param  array<int, float>  $basketPrices
     * @param  array<int, float>  $returns
     * @param  array<int, float>  $units  items traded per hour in the bucket, only where known
     * @param  array<int, float>  $weights  reliability weight of each return
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
        array $basketPrices,
        array $returns,
        ?float $marketReturn,
        array $units,
        array $weights,
    ): array {
        $betas = $this->metricsRepository->getLatestBetas($metricsItem->id, $bucketStart);
        $history = $this->metricsRepository->getResidualHistory(
            $metricsItem->id,
            $bucketStart->subHours($this->bucketHours() * ((int) config('poe.metrics.window_buckets') - 1)),
            $bucketStart,
        );
        $minObservations = (int) config('poe.metrics.min_residual_observations');
        $rows = [];

        foreach ($basketPrices as $itemId => $price) {
            $logReturn = $returns[$itemId] ?? null;
            $residual = ($logReturn !== null && $marketReturn !== null && $weights[$itemId] > 0.0)
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
                'price' => $price,
                'log_return' => $logReturn,
                'residual' => $residual,
                'residual_mad' => count($window) >= $minObservations ? $this->statistics->scaledMad($window) : null,
                'observations' => count($window),
                'units' => $units[$itemId] ?? null,
                'return_weight' => $weights[$itemId] ?? null,
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
     * @param  array<int, list<array{price: float, units: float|null}>>  $samplesByItem
     * @return array<int, array{price: float, units?: float}>
     */
    private function summarizeBucket(array $samplesByItem, int $excludedItemId): array
    {
        $summary = [];

        foreach ($samplesByItem as $itemId => $samples) {
            if ($itemId === $excludedItemId) {
                continue;
            }

            $summary[$itemId] = ['price' => $this->statistics->median(array_column($samples, 'price'))];

            $knownUnits = array_filter(array_column($samples, 'units'), static fn (?float $units): bool => $units !== null);

            if ($knownUnits !== []) {
                $summary[$itemId]['units'] = array_sum($knownUnits) / (float) count($knownUnits);
            }
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
     * A return is as reliable as the less traded of the two prices it is made of.
     *
     * @param  array<int, float>  $returns
     * @param  array<int, float>  $currentUnits
     * @param  array<int, float>  $previousUnits
     * @return array<int, float>
     */
    private function returnWeights(array $returns, array $currentUnits, array $previousUnits): array
    {
        $weights = [];

        foreach (array_keys($returns) as $itemId) {
            $weights[$itemId] = min(
                $this->unitsWeight($currentUnits[$itemId] ?? null),
                $this->unitsWeight($previousUnits[$itemId] ?? null),
            );
        }

        return $weights;
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
