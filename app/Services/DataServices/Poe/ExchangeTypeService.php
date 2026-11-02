<?php

namespace App\Services\DataServices\Poe;

use App\DataTransferObjects\RepositoryObjects\ExchangeTypeDto;
use App\Enums\CacheTtlEnum;
use App\Enums\Tags\PoeVersionTagEnum;
use App\Models\Poe\Exchange\ExchangeType;
use App\Utility\Mapper\MapperServiceContract;
use Illuminate\Support\Facades\Cache;

final readonly class ExchangeTypeService implements ExchangeTypeServiceContract
{
    private const string BASE_TAG = 'exchange_item';

    public function __construct(
        private MapperServiceContract $mapper,
    ) {}

    /**
     * @return ExchangeTypeDto[]
     */
    #[\Override]
    public function getByVersion(int $versionId): array
    {
        return Cache::tags([
            PoeVersionTagEnum::poe_version->name.$versionId,
        ])
            ->remember(
                self::BASE_TAG.$versionId,
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
