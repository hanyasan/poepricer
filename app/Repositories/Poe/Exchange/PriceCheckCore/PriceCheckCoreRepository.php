<?php

namespace App\Repositories\Poe\Exchange\PriceCheckCore;

use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheckCoreDto;
use App\Models\Poe\Exchange\PriceCheckCore as PriceCheckCoreModel;
use App\Models\Poe\Exchange\PriceCheckCoreType;
use App\Utility\Mapper\MapperServiceContract;
use Carbon\Carbon;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final readonly class PriceCheckCoreRepository implements PriceCheckCoreRepositoryContract
{
    public function __construct(
        private PriceCheckCoreModel $model,
        private MapperServiceContract $mapper,
    ) {}

    #[\Override]
    public function find(int $id): PriceCheckCoreDto
    {
        /** @var PriceCheckCoreDto $dto */
        $dto = $this->mapper->mapEloquentModel(
            PriceCheckCoreDto::class,
            $this->model->newQuery()->with('priceCheckCoreTypes')->findOrFail($id),
        );

        return $dto;
    }

    /**
     * @param  int[]  $ids
     */
    #[\Override]
    public function deleteMany(array $ids): void
    {
        $this
            ->model
            ->newQuery()
            ->whereIn('id', $ids)
            ->delete();
    }

    #[\Override]
    public function getLatestByLeague(int $leagueId): PriceCheckCoreDto
    {
        /** @var PriceCheckCoreDto $dto */
        $dto = $this->mapper->mapEloquentModel(
            PriceCheckCoreDto::class,
            $this
                ->model
                ->newQuery()
                ->with('priceCheckCoreTypes')
                ->where('poe_league_id', $leagueId)
                ->latest()
                ->firstOrFail(),
        );

        return $dto;
    }

    /**
     * @param  array<int, array{
     *      rate: float,
     *      exchange_item_trade_id: string,
     *      name: string,
     * }>  $types
     */
    #[\Override]
    public function createCore(
        int $leagueId,
        array $types,
    ): PriceCheckCoreDto {
        /** @var PriceCheckCoreModel $core */
        $core = $this->model->newQuery()->create([
            'poe_league_id' => $leagueId,
            'created_at' => now(),
        ]);

        $core
            ->priceCheckCoreTypes()
            ->createMany($types);

        return $this->find($core->id);
    }

    /**
     * @param  array<int, int>  $coreIds
     * @return array<int, array{
     *     exchange_item_trade_id: string,
     *     type_name: string,
     *     avg_type_rate: float,
     * }>
     */
    #[\Override]
    public function getAvgCoreTypesData(array $coreIds): array
    {
        $avgTypes = PriceCheckCoreType::query()
            ->select([
                'exchange_item_trade_id',
                DB::raw('AVG(price_check_core_types.rate) as avg_type_rate'),
            ])
            ->whereIn('price_check_core_types.price_check_core_id', $coreIds)
            ->groupBy('price_check_core_types.exchange_item_trade_id');

        return PriceCheckCoreType::query()
            ->select([
                'price_check_core_types.exchange_item_trade_id',
                'price_check_core_types.name as type_name',
                'avg_type_rate',
            ])
            ->distinct()
            ->rightJoinSub($avgTypes, 'sub_ct', function (JoinClause $join) {
                $join->on('sub_ct.exchange_item_trade_id', '=', 'price_check_core_types.exchange_item_trade_id');
            })
            ->get()
            ->toBase()
            ->toArray();
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     poe_league_id: int,
     *     date: string,
     * }>
     */
    #[\Override]
    public function getLeagueDailyPeriodsCoreIds(Carbon $date): array
    {
        return $this->model->newQuery()
            ->select([
                'id',
                'poe_league_id',
                DB::raw("date(price_check_cores.created_at AT TIME ZONE 'UTC') as date"),
            ])
            ->whereDate(DB::raw("date(price_check_cores.created_at AT TIME ZONE 'UTC')"), '<=', $date->setTimezone('UTC'))
            ->get()
            ->toArray();
    }
}
