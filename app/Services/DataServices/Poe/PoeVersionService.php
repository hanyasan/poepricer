<?php

namespace App\Services\DataServices\Poe;

use App\DataTransferObjects\RepositoryObjects\PoeVersionDto;
use App\Enums\CacheTtlEnum;
use App\Models\Poe\PoeVersion;
use App\Utility\Mapper\MapperServiceContract;
use Illuminate\Support\Facades\Cache;

final readonly class PoeVersionService implements PoeVersionServiceContract
{
    private const string BASE_TAG = 'poe_version';

    public function __construct(
        private MapperServiceContract $mapper,
    ) {}

    /**
     * @return PoeVersionDto[]
     */
    #[\Override]
    public function getAll(): array
    {
        return Cache::remember(
            self::BASE_TAG,
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
