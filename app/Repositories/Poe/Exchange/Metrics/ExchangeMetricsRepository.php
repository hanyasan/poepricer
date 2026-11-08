<?php

namespace App\Repositories\Poe\Exchange\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeItemMetricDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMarketMetricDto;
use App\Models\Poe\Exchange\Metrics\ExchangeItemBeta;
use App\Models\Poe\Exchange\Metrics\ExchangeItemMetric;
use App\Models\Poe\Exchange\Metrics\ExchangeMarketMetric;
use App\Utility\Mapper\MapperServiceContract;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final readonly class ExchangeMetricsRepository implements ExchangeMetricsRepositoryContract
{
    private const int UPSERT_CHUNK_SIZE = 1000;

    public function __construct(
        private MapperServiceContract $mapper,
    ) {}

    /**
     * @return array<int, int>
     */
    #[\Override]
    public function getBasketItemIds(int $metricsItemId, CarbonImmutable $bucketStart): array
    {
        return ExchangeItemMetric::query()
            ->where('exchange_metrics_item_id', $metricsItemId)
            ->where('bucket_start', $this->timestamp($bucketStart))
            ->pluck('exchange_item_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, array{
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
     * }>  $rows
     */
    #[\Override]
    public function upsertItemMetrics(array $rows): void
    {
        foreach (array_chunk($rows, self::UPSERT_CHUNK_SIZE) as $chunk) {
            ExchangeItemMetric::query()->upsert(
                $chunk,
                ['exchange_item_id', 'exchange_metrics_item_id', 'bucket_start'],
                ['price', 'log_return', 'residual', 'residual_mad', 'observations', 'units', 'return_weight'],
            );
        }
    }

    /**
     * @param  array{
     *     poe_league_id: int,
     *     exchange_metrics_item_id: int,
     *     bucket_start: string,
     *     market_return: float,
     *     basket_size: int,
     *     basket_total: int,
     * }  $row
     */
    #[\Override]
    public function upsertMarketMetric(array $row): void
    {
        ExchangeMarketMetric::query()->upsert(
            [$row],
            ['exchange_metrics_item_id', 'bucket_start'],
            ['poe_league_id', 'market_return', 'basket_size', 'basket_total'],
        );
    }

    /**
     * @return array<int, float>
     */
    #[\Override]
    public function getLatestBetas(int $metricsItemId, CarbonImmutable $asOf, array $itemIds = []): array
    {
        $latest = ExchangeItemBeta::query()
            ->where('exchange_metrics_item_id', $metricsItemId)
            ->where('calculated_at', '<=', $this->timestamp($asOf))
            ->when($itemIds !== [], static fn (Builder $query) => $query->whereIn('exchange_item_id', $itemIds))
            ->groupBy('exchange_item_id')
            ->select([
                'exchange_item_id',
                DB::raw('MAX(calculated_at) as latest_calculated_at'),
            ]);

        $betas = [];

        $rows = ExchangeItemBeta::query()
            ->joinSub($latest, 'latest', static function (JoinClause $join): void {
                $join->on('latest.exchange_item_id', '=', 'exchange_item_betas.exchange_item_id')
                    ->on('latest.latest_calculated_at', '=', 'exchange_item_betas.calculated_at');
            })
            ->where('exchange_item_betas.exchange_metrics_item_id', $metricsItemId)
            ->select(['exchange_item_betas.exchange_item_id', 'exchange_item_betas.beta'])
            ->get();

        foreach ($rows as $row) {
            $betas[$row->exchange_item_id] = $row->beta;
        }

        return $betas;
    }

    /**
     * @return array<int, array<int, float>>
     */
    #[\Override]
    public function getResidualHistory(int $metricsItemId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $history = [];

        $rows = ExchangeItemMetric::query()
            ->where('exchange_metrics_item_id', $metricsItemId)
            ->where('bucket_start', '>=', $this->timestamp($from))
            ->where('bucket_start', '<', $this->timestamp($to))
            ->whereNotNull('residual')
            ->orderBy('bucket_start')
            ->select(['exchange_item_id', 'residual'])
            ->cursor();

        foreach ($rows as $row) {
            $history[$row->exchange_item_id][] = (float) $row->residual;
        }

        return $history;
    }

    /**
     * @return array<int, array<int, array{market: float, item: float}>>
     */
    #[\Override]
    public function getReturnPairs(int $metricsItemId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $pairs = [];

        $rows = ExchangeItemMetric::query()
            ->join('exchange_market_metrics', static function (JoinClause $join): void {
                $join->on('exchange_market_metrics.exchange_metrics_item_id', '=', 'exchange_item_metrics.exchange_metrics_item_id')
                    ->on('exchange_market_metrics.bucket_start', '=', 'exchange_item_metrics.bucket_start');
            })
            ->where('exchange_item_metrics.exchange_metrics_item_id', $metricsItemId)
            ->where('exchange_item_metrics.bucket_start', '>=', $this->timestamp($from))
            ->where('exchange_item_metrics.bucket_start', '<', $this->timestamp($to))
            ->whereNotNull('exchange_item_metrics.log_return')
            ->where(static function (Builder $query): void {
                $query->whereNull('exchange_item_metrics.return_weight')
                    ->orWhere('exchange_item_metrics.return_weight', '>', 0);
            })
            ->orderBy('exchange_item_metrics.bucket_start')
            ->select([
                'exchange_item_metrics.exchange_item_id',
                'exchange_item_metrics.log_return',
                'exchange_market_metrics.market_return',
            ])
            ->cursor();

        foreach ($rows as $row) {
            $pairs[$row->exchange_item_id][] = [
                'market' => (float) $row->getAttribute('market_return'),
                'item' => (float) $row->log_return,
            ];
        }

        return $pairs;
    }

    #[\Override]
    public function findLatestMarketMetric(int $metricsItemId): ?ExchangeMarketMetricDto
    {
        $metric = ExchangeMarketMetric::query()
            ->where('exchange_metrics_item_id', $metricsItemId)
            ->orderByDesc('bucket_start')
            ->first();

        if ($metric === null) {
            return null;
        }

        /** @var ExchangeMarketMetricDto $dto */
        $dto = $this->mapper->mapEloquentModel(ExchangeMarketMetricDto::class, $metric);

        return $dto;
    }

    #[\Override]
    public function findMarketMetric(int $metricsItemId, CarbonImmutable $bucketStart): ?ExchangeMarketMetricDto
    {
        $metric = ExchangeMarketMetric::query()
            ->where('exchange_metrics_item_id', $metricsItemId)
            ->where('bucket_start', $this->timestamp($bucketStart))
            ->first();

        if ($metric === null) {
            return null;
        }

        /** @var ExchangeMarketMetricDto $dto */
        $dto = $this->mapper->mapEloquentModel(ExchangeMarketMetricDto::class, $metric);

        return $dto;
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function getMarketBucketStarts(int $metricsItemId, int $limit): array
    {
        return ExchangeMarketMetric::query()
            ->where('exchange_metrics_item_id', $metricsItemId)
            ->orderByDesc('bucket_start')
            ->limit($limit)
            ->pluck('bucket_start')
            ->map(static fn (mixed $bucketStart): string => (string) $bucketStart)
            ->all();
    }

    /**
     * @return ExchangeItemMetricDto[]
     */
    #[\Override]
    public function getBucketReturns(int $metricsItemId, CarbonImmutable $bucketStart, bool $onlyReliableReturns): array
    {
        $query = $this->bucketItemMetrics($metricsItemId, $bucketStart)->whereNotNull('log_return');

        return $this->mapItemMetrics($onlyReliableReturns ? $this->onlyReliableReturns($query) : $query);
    }

    /**
     * @return ExchangeMarketMetricDto[]
     */
    #[\Override]
    public function getMarketSeries(int $metricsItemId, ?CarbonImmutable $after): array
    {
        $query = ExchangeMarketMetric::query()
            ->where('exchange_metrics_item_id', $metricsItemId)
            ->orderBy('bucket_start');

        if ($after !== null) {
            $query->where('bucket_start', '>', $this->timestamp($after));
        }

        return $this->mapper->mapEloquentCollection(ExchangeMarketMetricDto::class, $query->get());
    }

    /**
     * @return ExchangeItemMetricDto[]
     */
    #[\Override]
    public function getTopGainers(int $metricsItemId, CarbonImmutable $bucketStart, int $limit): array
    {
        return $this->mapItemMetrics(
            $this->onlyReliableReturns($this->bucketItemMetrics($metricsItemId, $bucketStart))
                ->where('log_return', '>', 0)
                ->orderByDesc('log_return')
                ->limit($limit),
        );
    }

    /**
     * @return ExchangeItemMetricDto[]
     */
    #[\Override]
    public function getTopLosers(int $metricsItemId, CarbonImmutable $bucketStart, int $limit): array
    {
        return $this->mapItemMetrics(
            $this->onlyReliableReturns($this->bucketItemMetrics($metricsItemId, $bucketStart))
                ->where('log_return', '<', 0)
                ->orderBy('log_return')
                ->limit($limit),
        );
    }

    /**
     * @return ExchangeItemMetricDto[]
     */
    #[\Override]
    public function getUnusualMoves(
        int $metricsItemId,
        CarbonImmutable $bucketStart,
        float $multiplier,
        int $limit,
    ): array {
        return $this->mapItemMetrics(
            $this->bucketItemMetrics($metricsItemId, $bucketStart)
                ->whereNotNull('residual')
                ->where('residual_mad', '>', 0)
                ->whereRaw('ABS(residual) > ? * residual_mad', [$multiplier])
                ->orderByRaw('ABS(residual) / residual_mad DESC')
                ->limit($limit),
        );
    }

    /**
     * @param  array<int, array{
     *     exchange_item_id: int,
     *     exchange_metrics_item_id: int,
     *     calculated_at: string,
     *     beta: float,
     *     raw_beta: float,
     *     r_squared: float|null,
     *     observations: int,
     * }>  $rows
     */
    #[\Override]
    public function upsertBetas(array $rows): void
    {
        foreach (array_chunk($rows, self::UPSERT_CHUNK_SIZE) as $chunk) {
            ExchangeItemBeta::query()->upsert(
                $chunk,
                ['exchange_item_id', 'exchange_metrics_item_id', 'calculated_at'],
                ['beta', 'raw_beta', 'r_squared', 'observations'],
            );
        }
    }

    /**
     * @return Builder<ExchangeItemMetric>
     */
    private function bucketItemMetrics(int $metricsItemId, CarbonImmutable $bucketStart): Builder
    {
        return ExchangeItemMetric::query()
            ->with('exchangeItem')
            ->where('exchange_metrics_item_id', $metricsItemId)
            ->where('bucket_start', $this->timestamp($bucketStart));
    }

    /**
     * Leaves out the returns of thinly traded items, whose weight is zero. A null
     * weight means the volume was unknown and the return is trusted.
     *
     * @param  Builder<ExchangeItemMetric>  $query
     * @return Builder<ExchangeItemMetric>
     */
    private function onlyReliableReturns(Builder $query): Builder
    {
        return $query->where(static function (Builder $query): void {
            $query->whereNull('return_weight')->orWhere('return_weight', '>', 0);
        });
    }

    /**
     * @param  Builder<ExchangeItemMetric>  $query
     * @return ExchangeItemMetricDto[]
     */
    private function mapItemMetrics(Builder $query): array
    {
        return $this->mapper->mapEloquentCollection(ExchangeItemMetricDto::class, $query->get());
    }

    private function timestamp(CarbonImmutable $moment): string
    {
        return $moment->utc()->format('Y-m-d H:i:sP');
    }
}
