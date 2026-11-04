<?php

namespace App\Services\NinjaImport;

use App\DataTransferObjects\RepositoryObjects\League\ExtendedPoeLeagueDto;

interface NinjaImportServiceContract
{
    public function checkExchangePrices(
        ExtendedPoeLeagueDto $league,
    ): void;

    public function updateAllLeaguesFromNinja(): void;
}
