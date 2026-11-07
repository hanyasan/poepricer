<?php

namespace App\Repositories\Poe\Exchange\ExchangeItem;

use App\DataTransferObjects\RepositoryObjects\Exchange\ExchangeItemDto;

interface ExchangeItemRepositoryContract
{
    /**
     * @return ExchangeItemDto[]
     */
    public function getAllByLeague(int $leagueId): array;

    /**
     * @param  array<int, array{
     *     name: string,
     *     trade_id: string,
     *     poe_league_id: int,
     *     poe_category: string,
     * }>  $insertData
     */
    public function upsert(array $insertData): void;

    public function findByTradeId(int $leagueId, string $tradeId): ?ExchangeItemDto;
}
