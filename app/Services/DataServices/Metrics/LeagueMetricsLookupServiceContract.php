<?php

namespace App\Services\DataServices\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMetricsItemDto;
use App\DataTransferObjects\RepositoryObjects\League\PoeLeagueDto;
use App\Exceptions\League\LeagueNotFoundException;
use Illuminate\Validation\ValidationException;

interface LeagueMetricsLookupServiceContract
{
    /**
     * @throws LeagueNotFoundException when there is no active league with the slug
     */
    public function findLeague(string $slug): PoeLeagueDto;

    /**
     * Metrics items of the league with the default main currency first.
     *
     * @return ExchangeMetricsItemDto[]
     */
    public function metricsItems(int $leagueId): array;

    /**
     * The metrics item of the currency, the first one when the currency is null,
     * null when the league has no metrics items.
     *
     * @throws ValidationException when the currency is not a metric currency of the league
     */
    public function pickMetricsItem(int $leagueId, ?string $currency): ?ExchangeMetricsItemDto;
}
