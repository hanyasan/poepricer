<?php

namespace App\Repositories\Poe\Exchange\ExchangeItem;

use App\DataTransferObjects\RepositoryObjects\Exchange\ExchangeItemDto;
use App\Models\Poe\Exchange\ExchangeItem as ExchangeItemModel;
use App\Utility\Mapper\MapperServiceContract;

final readonly class ExchangeItemRepository implements ExchangeItemRepositoryContract
{
    public function __construct(
        private ExchangeItemModel $model,
        private MapperServiceContract $mapper,
    ) {}

    /**
     * @return ExchangeItemDto[]
     */
    #[\Override]
    public function getAllByLeague(int $leagueId): array
    {
        return $this->mapper->mapEloquentCollection(
            ExchangeItemDto::class,
            $this->model->newQuery()->where('poe_league_id', $leagueId)->get(),
        );
    }

    /**
     * @param  array<int, array{
     *     name: string,
     *     trade_id: string,
     *     poe_league_id: int,
     *     poe_category: string,
     * }>  $insertData
     */
    #[\Override]
    public function upsert(array $insertData): void
    {
        $this->model->newQuery()->upsert(
            $insertData,
            ['trade_id', 'poe_league_id'],
            ['name', 'poe_category'],
        );
    }

    #[\Override]
    public function findByTradeId(int $leagueId, string $tradeId): ?ExchangeItemDto
    {
        $item = $this->model->newQuery()
            ->where('poe_league_id', $leagueId)
            ->where('trade_id', $tradeId)
            ->first();

        return $item === null
            ? null
            : $this->mapper->mapEloquentModel(ExchangeItemDto::class, $item);
    }
}
