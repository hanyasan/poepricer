<?php

namespace App\Repositories\Poe\Exchange\PriceCheck;

use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheckCoreDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheckDto;
use App\Models\Poe\Exchange\ExchangeItemPrice;
use App\Models\Poe\Exchange\PriceCheck;
use App\Models\Poe\Exchange\PriceCheckCore;
use App\Utility\Mapper\MapperServiceContract;
use Illuminate\Support\Facades\DB;

final readonly class PriceCheckRepository implements PriceCheckRepositoryContract
{
    public function __construct(
        private MapperServiceContract $mapper,
    ) {}

    #[\Override]
    public function deleteMany(array $ids): void
    {
        PriceCheck::query()
            ->whereIn('id', $ids)
            ->delete();
    }

    #[\Override]
    public function createPriceCheck(int $leagueId): PriceCheckDto
    {
        $model = PriceCheck::query()->create([
            'poe_league_id' => $leagueId,
            'created_at' => now(),
        ]);

        $model->refresh();

        return $this->mapper->mapEloquentModel(
            PriceCheckDto::class,
            $model,
        );
    }

    /**
     * @param  array<int, array{
     *      rate: float,
     *      exchange_item_trade_id: string,
     *      name: string,
     * }>  $types
     */
    #[\Override]
    public function createCore(
        int $priceCheckId,
        int $exchangeTypeId,
        array $types,
    ): PriceCheckCoreDto {
        $core = DB::transaction(function () use ($priceCheckId, $exchangeTypeId, $types) {
            $core = PriceCheckCore::query()->create([
                'price_check_id' => $priceCheckId,
                'exchange_type_id' => $exchangeTypeId,
            ]);

            $core
                ->priceCheckCoreTypes()
                ->createMany($types);

            return $core;
        });

        $core->loadMissing('priceCheckCoreTypes');

        return $this->mapper->mapEloquentModel(
            PriceCheckCoreDto::class,
            $core,
        );
    }

    /**
     * @param  array<int, array{
     *     rate: float,
     *     price_check_core_id: int,
     *     exchange_item_id: int,
     * }>  $pricesData
     */
    #[\Override]
    public function insertPrices(array $pricesData): void
    {
        ExchangeItemPrice::query()
            ->insert(array_map(static function (array $priceData) {
                return [
                    'rate' => $priceData['rate'],
                    'exchange_item_id' => $priceData['exchange_item_id'],
                    'price_check_core_id' => $priceData['price_check_core_id'],
                ];
            }, $pricesData));
    }
}
