<?php

namespace App\Services\DataServices\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMetricsItemDto;
use App\Repositories\Poe\Exchange\Metrics\ExchangeBucketPriceRepositoryContract;
use App\Repositories\Poe\Exchange\Metrics\ExchangeMetricsItemRepositoryContract;
use App\Repositories\Poe\Exchange\Metrics\ExchangeMetricsRepositoryContract;
use App\Utility\Statistics\RobustStatistics;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

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

        foreach ($this->metricsItemRepository->getAllByLeague($leagueId) as $metricsItem) {
            DB::transaction(function () use ($metricsItem, $bucketStart): void {
                $this->collectForMetricsItem($metricsItem, $bucketStart);
            });
        }
    }

    #[\Override]
    public function recalculateBetas(int $leagueId, CarbonImmutable $asOf): void
    {
        $windowStart = $asOf->subHours($this->bucketHours() * (int) config('poe.metrics.window_buckets'));

        foreach ($this->metricsItemRepository->getAllByLeague($leagueId) as $metricsItem) {
            $rows = $this->buildBetaRows($metricsItem, $windowStart, $asOf);

            $this->metricsRepository->upsertBetas($rows);
        }
    }

    private function collectForMetricsItem(ExchangeMetricsItemDto $metricsItem, CarbonImmutable $bucketStart): void
    {
        $previousStart = $bucketStart->subHours($this->bucketHours());
        $currencyTradeId = $metricsItem->exchange_item->trade_id;

        $currentPrices = $this->medianPrices(
            $this->bucketPriceRepository->getPricesByItem(
                $metricsItem->poe_league_id,
                $currencyTradeId,
                $bucketStart,
                $bucketStart->addHours($this->bucketHours()),
            ),
            $metricsItem->exchange_item_id,
        );

        if ($currentPrices === []) {
            return;
        }

        $previousPrices = $this->medianPrices(
            $this->bucketPriceRepository->getPricesByItem(
                $metricsItem->poe_league_id,
                $currencyTradeId,
                $previousStart,
                $bucketStart,
            ),
            $metricsItem->exchange_item_id,
        );

        $basketPrices = $this->pickBasket(
            $currentPrices,
            array_flip($this->metricsRepository->getBasketItemIds($metricsItem->id, $previousStart)),
            $metricsItem->min_price,
        );

        $returns = $this->calculateReturns($basketPrices, $previousPrices);
        $marketReturn = $returns === [] ? null : $this->statistics->median($returns);

        $this->metricsRepository->upsertItemMetrics(
            $this->buildItemMetricRows($metricsItem, $bucketStart, $basketPrices, $returns, $marketReturn),
        );

        if ($marketReturn !== null) {
            $this->metricsRepository->upsertMarketMetric([
                'poe_league_id' => $metricsItem->poe_league_id,
                'exchange_metrics_item_id' => $metricsItem->id,
                'bucket_start' => $bucketStart->format(self::TIMESTAMP_FORMAT),
                'market_return' => $marketReturn,
                'basket_size' => count($returns),
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
     * @return array<int, array{
     *     exchange_item_id: int,
     *     exchange_metrics_item_id: int,
     *     bucket_start: string,
     *     price: float,
     *     log_return: float|null,
     *     residual: float|null,
     *     residual_mad: float|null,
     *     observations: int,
     * }>
     */
    private function buildItemMetricRows(
        ExchangeMetricsItemDto $metricsItem,
        CarbonImmutable $bucketStart,
        array $basketPrices,
        array $returns,
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

        foreach ($basketPrices as $itemId => $price) {
            $logReturn = $returns[$itemId] ?? null;
            $residual = ($logReturn !== null && $marketReturn !== null)
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
     * @param  array<int, array<int, float>>  $pricesByItem
     * @return array<int, float>
     */
    private function medianPrices(array $pricesByItem, int $excludedItemId): array
    {
        $medians = [];

        foreach ($pricesByItem as $itemId => $prices) {
            if ($itemId !== $excludedItemId) {
                $medians[$itemId] = $this->statistics->median($prices);
            }
        }

        return $medians;
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
