<?php

namespace App\Repositories\Poe\Exchange\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeItemBetaDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemMetricRowDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemSeriesRowDto;
use App\DataTransferObjects\RepositoryObjects\PaginatedResultDto;
use App\DataTransferObjects\ServiceObjects\PaginationDto;
use App\Models\Poe\Exchange\Metrics\ExchangeItemBeta;
use App\Models\Poe\Exchange\Metrics\ExchangeItemMetric;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

final readonly class ExchangeItemDetailRepository implements ExchangeItemDetailRepositoryContract
{
    #[\Override]
    public function findMetric(int $metricsItemId, int $itemId, CarbonImmutable $bucketStart): ?ItemMetricRowDto
    {
        $row = ExchangeItemMetric::query()
            ->where('exchange_metrics_item_id', $metricsItemId)
            ->where('exchange_item_id', $itemId)
            ->where('bucket_start', self::timestamp($bucketStart))
            ->toBase()
            ->first();

        return $row === null ? null : $this->metricRow($row);
    }

    #[\Override]
    public function findLatestBeta(int $metricsItemId, int $itemId): ?ExchangeItemBetaDto
    {
        $row = ExchangeItemBeta::query()
            ->where('exchange_metrics_item_id', $metricsItemId)
            ->where('exchange_item_id', $itemId)
            ->orderByDesc('calculated_at')
            ->toBase()
            ->first();

        return $row === null ? null : $this->betaRow($row);
    }

    /**
     * @return ExchangeItemBetaDto[]
     */
    #[\Override]
    public function getBetaHistory(int $metricsItemId, int $itemId, ?CarbonImmutable $after): array
    {
        $query = ExchangeItemBeta::query()
            ->where('exchange_metrics_item_id', $metricsItemId)
            ->where('exchange_item_id', $itemId)
            ->orderBy('calculated_at')
            ->toBase();

        if ($after !== null) {
            $query->where('calculated_at', '>', self::timestamp($after));
        }

        return $query->get()
            ->map(fn (object $row): ExchangeItemBetaDto => $this->betaRow($row))
            ->all();
    }

    /**
     * @return ItemSeriesRowDto[]
     */
    #[\Override]
    public function getSeries(int $metricsItemId, int $itemId, ?CarbonImmutable $after): array
    {
        return $this->itemBuckets($metricsItemId, $itemId, $after)
            ->orderBy('bucket_start')
            ->select(['bucket_start', 'price', 'residual', 'residual_mad'])
            ->get()
            ->map(static fn (object $row): ItemSeriesRowDto => new ItemSeriesRowDto(
                (string) $row->bucket_start,
                (float) $row->price,
                $row->residual === null ? null : (float) $row->residual,
                $row->residual_mad === null ? null : (float) $row->residual_mad,
            ))
            ->all();
    }

    /**
     * @return PaginatedResultDto<ItemMetricRowDto>
     */
    #[\Override]
    public function paginateMetrics(
        int $metricsItemId,
        int $itemId,
        ?CarbonImmutable $after,
        PaginationDto $pagination,
    ): PaginatedResultDto {
        $total = $this->itemBuckets($metricsItemId, $itemId, $after)->count();

        if ($total === 0) {
            return new PaginatedResultDto([], 0);
        }

        $rows = $this->itemBuckets($metricsItemId, $itemId, $after)
            ->orderByDesc('bucket_start')
            ->offset($pagination->offset())
            ->limit($pagination->perPage)
            ->get()
            ->map(fn (object $row): ItemMetricRowDto => $this->metricRow($row))
            ->all();

        return new PaginatedResultDto($rows, $total);
    }

    private function itemBuckets(int $metricsItemId, int $itemId, ?CarbonImmutable $after): Builder
    {
        $query = ExchangeItemMetric::query()
            ->where('exchange_metrics_item_id', $metricsItemId)
            ->where('exchange_item_id', $itemId)
            ->toBase();

        if ($after !== null) {
            $query->where('bucket_start', '>', self::timestamp($after));
        }

        return $query;
    }

    private function metricRow(object $row): ItemMetricRowDto
    {
        return new ItemMetricRowDto(
            (string) $row->bucket_start,
            (float) $row->price,
            $row->log_return === null ? null : (float) $row->log_return,
            $row->residual === null ? null : (float) $row->residual,
            $row->residual_mad === null ? null : (float) $row->residual_mad,
            (int) $row->observations,
            $row->units === null ? null : (float) $row->units,
            $row->return_weight === null ? null : (float) $row->return_weight,
        );
    }

    private function betaRow(object $row): ExchangeItemBetaDto
    {
        return new ExchangeItemBetaDto(
            (string) $row->calculated_at,
            (float) $row->beta,
            (float) $row->raw_beta,
            $row->r_squared === null ? null : (float) $row->r_squared,
            (int) $row->observations,
        );
    }

    private static function timestamp(CarbonImmutable $moment): string
    {
        return $moment->utc()->format('Y-m-d H:i:sP');
    }
}
