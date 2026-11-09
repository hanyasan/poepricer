<?php

namespace App\Repositories\Poe\Exchange\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemPricePointDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemTableRowDto;
use App\DataTransferObjects\RepositoryObjects\PaginatedResultDto;
use App\DataTransferObjects\ServiceObjects\LeagueItems\ItemsFilterDto;
use App\DataTransferObjects\ServiceObjects\LeagueItems\ItemsSortDto;
use App\DataTransferObjects\ServiceObjects\PaginationDto;
use App\Enums\ItemsSortFieldEnum;
use App\Enums\SortDirectionEnum;
use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\Exchange\Metrics\ExchangeItemMetric;
use App\Models\Poe\Exchange\PriceCheck;
use App\Utility\Mapper\MapperServiceContract;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final readonly class ExchangeItemTableRepository implements ExchangeItemTableRepositoryContract
{
    public function __construct(
        private MapperServiceContract $mapper,
    ) {}

    /**
     * @return PaginatedResultDto<ItemTableRowDto>
     */
    #[\Override]
    public function paginate(
        int $leagueId,
        int $metricsItemId,
        string $currencyTradeId,
        CarbonImmutable $bucketStart,
        ItemsFilterDto $filter,
        ItemsSortDto $sort,
        PaginationDto $pagination,
    ): PaginatedResultDto {
        $total = $this->filteredItems($leagueId, $filter)->count();

        if ($total === 0) {
            return new PaginatedResultDto([], 0);
        }

        $rows = $this->filteredItems($leagueId, $filter)
            ->leftJoin('exchange_item_metrics as metrics', static function (JoinClause $join) use ($metricsItemId, $bucketStart): void {
                $join->on('metrics.exchange_item_id', '=', 'exchange_items.id')
                    ->where('metrics.exchange_metrics_item_id', '=', $metricsItemId)
                    ->where('metrics.bucket_start', '=', self::timestamp($bucketStart));
            })
            ->leftJoinLateral($this->latestBeta($metricsItemId), 'betas')
            ->leftJoinLateral($this->latestPrice($leagueId, $currencyTradeId), 'latest_price')
            ->select([
                'exchange_items.id',
                'exchange_items.name',
                'exchange_items.trade_id',
                'exchange_items.poe_category',
                'metrics.price',
                'metrics.log_return',
                'metrics.residual',
                'metrics.residual_mad',
                'metrics.observations',
                'metrics.units',
                'metrics.return_weight',
                'betas.beta',
                DB::raw('COALESCE(latest_price.price, metrics.price) as current_price'),
            ])
            ->orderByRaw($this->sortExpression($sort->field).' '.$this->sortDirection($sort->direction).' NULLS LAST')
            ->orderBy('exchange_items.id')
            ->offset($pagination->offset())
            ->limit($pagination->perPage)
            ->get();

        return new PaginatedResultDto(
            $this->mapper->mapEloquentCollection(ItemTableRowDto::class, $rows),
            $total,
        );
    }

    /**
     * @param  array<int, int>  $itemIds
     * @return array<int, ItemPricePointDto[]>
     */
    #[\Override]
    public function getPriceSeries(int $metricsItemId, array $itemIds, CarbonImmutable $after): array
    {
        if ($itemIds === []) {
            return [];
        }

        $series = [];

        $rows = ExchangeItemMetric::query()
            ->where('exchange_metrics_item_id', $metricsItemId)
            ->whereIn('exchange_item_id', $itemIds)
            ->where('bucket_start', '>', self::timestamp($after))
            ->orderBy('bucket_start')
            ->select(['exchange_item_id', 'bucket_start', 'price'])
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            $series[(int) $row->exchange_item_id][] = new ItemPricePointDto(
                (string) $row->bucket_start,
                (float) $row->price,
            );
        }

        return $series;
    }

    /**
     * The price of the joined item in the latest price check of the league, measured in
     * the requested currency, as the median over the cores of the check. It is the
     * freshest price and is read apart from the buckets, which only ever hold closed spans.
     */
    private function latestPrice(int $leagueId, string $currencyTradeId): QueryBuilder
    {
        return DB::table('exchange_item_prices as latest_price')
            ->join('price_check_cores as latest_core', 'latest_core.id', '=', 'latest_price.price_check_core_id')
            ->join('price_check_core_types as latest_core_type', static function (JoinClause $join) use ($currencyTradeId): void {
                $join->on('latest_core_type.price_check_core_id', '=', 'latest_core.id')
                    ->where('latest_core_type.exchange_item_trade_id', '=', $currencyTradeId);
            })
            ->select(DB::raw('percentile_cont(0.5) within group (order by latest_price.rate * latest_core_type.rate) as price'))
            ->whereColumn('latest_price.exchange_item_id', 'exchange_items.id')
            ->where('latest_core_type.rate', '>', 0)
            ->whereIn('latest_core.price_check_id', PriceCheck::query()
                ->select('id')
                ->where('poe_league_id', $leagueId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(1));
    }

    /**
     * The latest beta of the joined item, read backwards from the unique index of the
     * betas so the cost does not grow with the history of recalculations.
     */
    private function latestBeta(int $metricsItemId): QueryBuilder
    {
        return DB::table('exchange_item_betas as latest')
            ->select('latest.beta')
            ->whereColumn('latest.exchange_item_id', 'exchange_items.id')
            ->where('latest.exchange_metrics_item_id', $metricsItemId)
            ->orderByDesc('latest.calculated_at')
            ->limit(1);
    }

    /**
     * @return Builder<ExchangeItem>
     */
    private function filteredItems(int $leagueId, ItemsFilterDto $filter): Builder
    {
        $query = ExchangeItem::query()->where('exchange_items.poe_league_id', $leagueId);

        if ($filter->search !== null && $filter->search !== '') {
            $query->whereRaw('exchange_items.name ILIKE ?', ['%'.$this->escapeLike($filter->search).'%']);
        }

        if ($filter->uncategorized) {
            $query->where(static function (Builder $query): void {
                $query->whereNull('exchange_items.poe_category')->orWhere('exchange_items.poe_category', '');
            });
        } elseif ($filter->category !== null) {
            $query->where('exchange_items.poe_category', $filter->category);
        }

        return $query;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Fixed SQL of the sorted field, never built from the request.
     *
     * @return literal-string
     */
    private function sortExpression(ItemsSortFieldEnum $field): string
    {
        return match ($field) {
            ItemsSortFieldEnum::Name => 'exchange_items.name',
            ItemsSortFieldEnum::Price => 'COALESCE(latest_price.price, metrics.price)',
            ItemsSortFieldEnum::LogReturn => 'metrics.log_return',
            ItemsSortFieldEnum::Residual => 'metrics.residual',
            ItemsSortFieldEnum::ResidualMad => 'metrics.residual_mad',
            ItemsSortFieldEnum::Beta => 'betas.beta',
            ItemsSortFieldEnum::Observations => 'metrics.observations',
            ItemsSortFieldEnum::Units => 'metrics.units',
            ItemsSortFieldEnum::UnusualRatio => 'ABS(metrics.residual) / NULLIF(metrics.residual_mad, 0)',
        };
    }

    /**
     * @return literal-string
     */
    private function sortDirection(SortDirectionEnum $direction): string
    {
        return $direction === SortDirectionEnum::Asc ? 'ASC' : 'DESC';
    }

    private static function timestamp(CarbonImmutable $moment): string
    {
        return $moment->utc()->format('Y-m-d H:i:sP');
    }
}
