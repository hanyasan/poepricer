<?php

namespace App\Services\NinjaImport;

use App\DataTransferObjects\RepositoryObjects\PoeVersionDto;

interface NinjaLeaguePricerServiceContract
{
    public function importPickedForVersion(PoeVersionDto $version): void;
}
