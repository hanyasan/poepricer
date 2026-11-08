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

    /**
     * Distinct non-empty categories of the items of the league, alphabetically.
     *
     * @return string[]
     */
    public function getCategoriesByLeague(int $leagueId): array;

    /**
     * Whether the league has items whose category is null or empty.
     */
    public function hasUncategorizedByLeague(int $leagueId): bool;
}
