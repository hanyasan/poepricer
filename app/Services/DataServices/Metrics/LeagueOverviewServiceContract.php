<?php

namespace App\Services\DataServices\Metrics;

use App\DataTransferObjects\ServiceObjects\LeagueOverview\LeagueCurrencyDto;
use App\DataTransferObjects\ServiceObjects\LeagueOverview\LeagueOverviewDto;
use App\Enums\MetricPeriodEnum;
use App\Exceptions\League\LeagueNotFoundException;
use Illuminate\Validation\ValidationException;

interface LeagueOverviewServiceContract
{
    /**
     * Metric currencies of the league, the default main currency first.
     *
     * @return LeagueCurrencyDto[]
     *
     * @throws LeagueNotFoundException
     */
    public function currencies(string $leagueSlug): array;

    /**
     * Overview of the league measured in the currency, the default main currency
     * when none is given.
     *
     * @throws LeagueNotFoundException
     * @throws ValidationException when the league has no such metric currency
     */
    public function overview(string $leagueSlug, ?string $currency, MetricPeriodEnum $period): LeagueOverviewDto;
}
