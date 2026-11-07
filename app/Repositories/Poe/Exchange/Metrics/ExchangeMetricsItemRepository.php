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

    #[\Override]
    public function existsForLeague(int $leagueId): bool
    {
        return ExchangeMetricsItem::query()
            ->where('poe_league_id', $leagueId)
            ->exists();
    }

    /**
     * @param  list<array{poe_league_id: int, exchange_item_id: int, min_price: float}>  $rows
     */
    #[\Override]
    public function insertMany(array $rows): void
    {
        ExchangeMetricsItem::query()->insertOrIgnore($rows);
    }

    #[\Override]
    public function existsForLeagueItem(int $leagueId, int $exchangeItemId): bool
    {
        return ExchangeMetricsItem::query()
            ->where('poe_league_id', $leagueId)
            ->where('exchange_item_id', $exchangeItemId)
            ->exists();
    }
}
