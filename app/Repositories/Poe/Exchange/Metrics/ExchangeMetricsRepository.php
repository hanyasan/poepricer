<?php

namespace App\Repositories\Poe\Exchange\Metrics;

use App\Models\Poe\Exchange\Metrics\ExchangeItemBeta;
use App\Models\Poe\Exchange\Metrics\ExchangeItemMetric;
use App\Models\Poe\Exchange\Metrics\ExchangeMarketMetric;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final readonly class ExchangeMetricsRepository implements ExchangeMetricsRepositoryContract
{
    private const int UPSERT_CHUNK_SIZE = 1000;

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
     * }>  $rows
     */
    #[\Override]
    public function upsertItemMetrics(array $rows): void
    {
        foreach (array_chunk($rows, self::UPSERT_CHUNK_SIZE) as $chunk) {
            ExchangeItemMetric::query()->upsert(
                $chunk,
                ['exchange_item_id', 'exchange_metrics_item_id', 'bucket_start'],
                ['price', 'log_return', 'residual', 'residual_mad', 'observations'],
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
     * }  $row
     */
    #[\Override]
    public function upsertMarketMetric(array $row): void
    {
        ExchangeMarketMetric::query()->upsert(
            [$row],
            ['exchange_metrics_item_id', 'bucket_start'],
            ['poe_league_id', 'market_return', 'basket_size'],
        );
    }

    /**
     * @return array<int, float>
     */
    #[\Override]
    public function getLatestBetas(int $metricsItemId, CarbonImmutable $asOf): array
    {
        $latest = ExchangeItemBeta::query()
            ->where('exchange_metrics_item_id', $metricsItemId)
            ->where('calculated_at', '<=', $this->timestamp($asOf))
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

    private function timestamp(CarbonImmutable $moment): string
    {
        return $moment->utc()->format('Y-m-d H:i:sP');
    }
}
