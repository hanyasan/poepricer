<?php

namespace App\Repositories\Poe\Exchange\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMetricsItemDto;

interface ExchangeMetricsItemRepositoryContract
{
    /**
     * @return ExchangeMetricsItemDto[]
     */
    public function getAllByLeague(int $leagueId): array;

    public function existsForLeague(int $leagueId): bool;

    /**
     * Rows that already exist for the league and item are left untouched.
     *
     * @param  list<array{poe_league_id: int, exchange_item_id: int, min_price: float}>  $rows
     */
    public function insertMany(array $rows): void;
}
