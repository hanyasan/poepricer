<?php

namespace App\Services\DataServices\Poe;

use App\DataTransferObjects\RepositoryObjects\ExchangeTypeDto;

interface ExchangeTypeServiceContract
{
    /**
     * @return ExchangeTypeDto[]
     */
    public function getByVersion(int $versionId): array;
}
