<?php

namespace App\Services\DataServices;

use App\DataTransferObjects\RepositoryObjects\PoeVersionDto;
use App\Enums\CacheTtlEnum;
use App\Models\Poe\PoeVersion;
use App\Utility\Mapper\MapperServiceContract;
use Illuminate\Support\Facades\Cache;

/** @psalm-suppress ClassMustBeFinal */
class PoeVersionService
{
    private string $baseTag = 'poe_version';

    public function __construct(
        private readonly MapperServiceContract $mapper,
    ) {}

    /**
     * @return PoeVersionDto[]
     */
    public function getAll(): array
    {
        return Cache::remember(
            $this->baseTag,
            CacheTtlEnum::DAY->jiggle(),
            function () {
                return $this->mapper->mapEloquentCollection(
                    PoeVersionDto::class,
                    PoeVersion::class::query()->get(),
                );
            }
        );
    }
}
