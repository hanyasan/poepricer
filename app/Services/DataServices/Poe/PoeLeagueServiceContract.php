<?php

namespace App\Services\DataServices\Poe;

use App\DataTransferObjects\RepositoryObjects\League\MainPoeLeagueDto;

interface PoeLeagueServiceContract
{
    /**
     * @return MainPoeLeagueDto[]
     */
    public function mainPageLeagues(): array;
}
