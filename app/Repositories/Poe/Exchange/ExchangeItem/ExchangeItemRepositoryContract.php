<?php

namespace App\Repositories\Poe\Exchange\ExchangeItem;

use App\DataTransferObjects\RepositoryObjects\Exchange\ExchangeItemDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\ExchangeItemWithPricesDto;

interface ExchangeItemRepositoryContract
{
    /**
     * @return ExchangeItemDto[]
     */
    public function getAllByLeague(int $leagueId): array;

    /**
     * @return ExchangeItemWithPricesDto[]
     */
    public function getAllByLeagueWithPriceByCore(int $leagueId, int $coreId): array;

    /**
     * @param  array<int, array{
     *     name: string,
     *     trade_id: string,
     *     poe_league_id: int,
     * }>  $insertData
     */
    public function upsert(array $insertData): void;
}
