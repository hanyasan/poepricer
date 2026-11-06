<?php

namespace App\Repositories\Poe\Exchange\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMetricsItemDto;

interface ExchangeMetricsItemRepositoryContract
{
    /**
     * @return ExchangeMetricsItemDto[]
     */
    public function getAllByLeague(int $leagueId): array;
}
