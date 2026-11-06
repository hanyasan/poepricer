<?php

namespace App\Repositories\Poe\Exchange\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMetricsItemDto;
use App\Models\Poe\Exchange\Metrics\ExchangeMetricsItem;
use App\Utility\Mapper\MapperServiceContract;

final readonly class ExchangeMetricsItemRepository implements ExchangeMetricsItemRepositoryContract
{
    public function __construct(
        private MapperServiceContract $mapper,
    ) {}

    /**
     * @return ExchangeMetricsItemDto[]
     */
    #[\Override]
    public function getAllByLeague(int $leagueId): array
    {
        return $this->mapper->mapEloquentCollection(
            ExchangeMetricsItemDto::class,
            ExchangeMetricsItem::query()
                ->with('exchangeItem')
                ->where('poe_league_id', $leagueId)
                ->orderBy('id')
                ->get(),
        );
    }
}
