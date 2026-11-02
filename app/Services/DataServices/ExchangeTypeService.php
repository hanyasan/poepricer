<?php

namespace App\Services\DataServices;

use App\DataTransferObjects\RepositoryObjects\ExchangeTypeDto;
use App\Enums\CacheTtlEnum;
use App\Enums\Tags\PoeVersionTagEnum;
use App\Models\Poe\Exchange\ExchangeType;
use App\Utility\Mapper\MapperServiceContract;
use Illuminate\Support\Facades\Cache;

/** @psalm-suppress ClassMustBeFinal */
class ExchangeTypeService
{
    private string $baseTag = 'exchange_item';

    public function __construct(
        private readonly MapperServiceContract $mapper,
    ) {}

    /**
     * @return ExchangeTypeDto[]
     */
    public function getByVersion(int $versionId): array
    {
        return Cache::tags([
            PoeVersionTagEnum::poe_version->name.$versionId,
        ])
            ->remember(
                $this->baseTag.$versionId,
                CacheTtlEnum::DAY->jiggle(),
                function () use ($versionId) {
                    return $this->mapper->mapEloquentCollection(
                        ExchangeTypeDto::class,
                        ExchangeType::query()->where('poe_version_id', $versionId)->get(),
                    );
                }
            );
    }
}
