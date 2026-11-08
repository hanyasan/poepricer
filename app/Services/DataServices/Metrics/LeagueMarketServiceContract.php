<?php

namespace App\Services\DataServices\Metrics;

use App\DataTransferObjects\ServiceObjects\MarketDistribution\MarketDistributionDto;
use App\Exceptions\League\LeagueNotFoundException;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

interface LeagueMarketServiceContract
{
    /**
     * Distribution of the item returns of one bucket, the latest one when none is given.
     * Items with a zero return weight are counted only when thinly traded ones are included.
     *
     * @throws LeagueNotFoundException
     * @throws ValidationException when the league has no such metric currency or no such bucket
     */
    public function distribution(
        string $leagueSlug,
        ?string $currency,
        ?CarbonImmutable $bucket,
        bool $includeThinlyTraded,
    ): MarketDistributionDto;
}
