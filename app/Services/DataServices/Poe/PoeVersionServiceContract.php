<?php

namespace App\Services\DataServices\Poe;

use App\DataTransferObjects\RepositoryObjects\PoeVersionDto;

interface PoeVersionServiceContract
{
    /**
     * @return PoeVersionDto[]
     */
    public function getAll(): array;
}
