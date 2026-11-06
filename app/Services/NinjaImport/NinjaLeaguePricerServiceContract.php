<?php

namespace App\Services\NinjaImport;

use App\DataTransferObjects\RepositoryObjects\League\ExtendedPoeLeagueDto;
use App\DataTransferObjects\RepositoryObjects\PoeVersionDto;
use Illuminate\Support\Collection;

interface NinjaLeaguePricerServiceContract
{
    public function importPickedForVersion(PoeVersionDto $version): void;

    /**
     * @return Collection<int, ExtendedPoeLeagueDto>
     */
    public function pickLeaguesForCheck(PoeVersionDto $version): Collection;
}
