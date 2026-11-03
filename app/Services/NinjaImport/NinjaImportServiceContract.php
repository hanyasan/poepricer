<?php

namespace App\Services\NinjaImport;

use App\DataTransferObjects\RepositoryObjects\League\PoeLeagueDto;
use App\DataTransferObjects\RepositoryObjects\PoeVersionDto;

interface NinjaImportServiceContract
{
    public function checkExchangePrices(
        PoeLeagueDto $league,
        PoeVersionDto $version,
    ): void;

    public function updateAllLeaguesFromNinja(): void;
}
