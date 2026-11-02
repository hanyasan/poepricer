<?php

namespace App\Repositories\Poe\Exchange\PriceCheckCore;

use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheckCoreDto;
use Carbon\Carbon;

interface PriceCheckCoreRepositoryContract
{
    public function find(int $id): PriceCheckCoreDto;

    /**
     * @param  int[]  $ids
     */
    public function deleteMany(array $ids): void;

    public function getLatestByLeague(int $leagueId): PriceCheckCoreDto;

    /**
     * @param  array<int, array{
     *      rate: float,
     *      exchange_item_trade_id: string,
     *      name: string,
     * }>  $types
     */
    public function createCore(
        int $leagueId,
        array $types,
    ): PriceCheckCoreDto;

    /**
     * @param  array<int, int>  $coreIds
     * @return array<int, array{
     *     exchange_item_trade_id: string,
     *     type_name: string,
     *     avg_type_rate: float,
     * }>
     */
    public function getAvgCoreTypesData(array $coreIds): array;

    /**
     * @return array<int, array{
     *     id: int,
     *     poe_league_id: int,
     *     date: string,
     * }>
     */
    public function getLeagueDailyPeriodsCoreIds(Carbon $date): array;
}
