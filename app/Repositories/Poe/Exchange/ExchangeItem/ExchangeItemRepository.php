<?php

namespace App\Repositories\Poe\Exchange\ExchangeItem;

use App\DataTransferObjects\RepositoryObjects\Exchange\ExchangeItemDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\ExchangeItemWithPricesDto;
use App\Models\Poe\Exchange\ExchangeItem as ExchangeItemModel;
use App\Utility\Mapper\MapperServiceContract;
use Illuminate\Database\Eloquent\Relations\Relation;

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
     * @return ExchangeItemWithPricesDto[]
     */
    #[\Override]
    public function getAllByLeagueWithPriceByCore(int $leagueId, int $coreId): array
    {
        return $this->mapper->mapEloquentCollection(
            ExchangeItemWithPricesDto::class,
            $this
                ->model
                ->newQuery()
                ->where('poe_league_id', $leagueId)
                ->with(['prices' => static function (Relation $query) use ($coreId) {
                    $query->where('price_check_core_id', $coreId);
                }])
                ->get(),
        );
    }

    /**
     * @param  array<int, array{
     *     name: string,
     *     trade_id: string,
     *     poe_league_id: int,
     * }>  $insertData
     */
    #[\Override]
    public function upsert(array $insertData): void
    {
        $this->model->newQuery()->upsert(
            $insertData,
            ['trade_id', 'poe_league_id'],
            ['name'],
        );
    }
}
