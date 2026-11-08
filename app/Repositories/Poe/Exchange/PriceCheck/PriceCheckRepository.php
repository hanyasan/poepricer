<?php

namespace App\Repositories\Poe\Exchange\PriceCheck;

use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheckCoreDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheckDto;
use App\Models\Poe\Exchange\ExchangeItemPrice;
use App\Models\Poe\Exchange\PriceCheck;
use App\Models\Poe\Exchange\PriceCheckCore;
use App\Models\Poe\Exchange\PriceCheckCoreType;
use App\Utility\Mapper\MapperServiceContract;
use Illuminate\Database\Query\JoinClause;
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
     *     volume?: float|null,
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
                    'volume' => $priceData['volume'] ?? null,
                    'exchange_item_id' => $priceData['exchange_item_id'],
                    'price_check_core_id' => $priceData['price_check_core_id'],
                ];
            }, $pricesData));
    }

    /**
     * @return array<int, int>
     */
    #[\Override]
    public function getCoreIdsWithoutCoreType(int $priceCheckId, string $tradeId): array
    {
        return PriceCheckCore::query()
            ->where('price_check_id', $priceCheckId)
            ->whereDoesntHave(
                'priceCheckCoreTypes',
                static fn ($query) => $query->where('exchange_item_trade_id', $tradeId),
            )
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @return array{rate: float, name: string}|null
     */
    #[\Override]
    public function findItemPrimaryPrice(int $priceCheckId, string $tradeId): ?array
    {
        $price = ExchangeItemPrice::query()
            ->join('exchange_items', 'exchange_items.id', '=', 'exchange_item_prices.exchange_item_id')
            ->join('price_check_cores', 'price_check_cores.id', '=', 'exchange_item_prices.price_check_core_id')
            ->where('price_check_cores.price_check_id', $priceCheckId)
            ->where('exchange_items.trade_id', $tradeId)
            ->orderBy('exchange_item_prices.id')
            ->select(['exchange_item_prices.rate', 'exchange_items.name'])
            ->first();

        if ($price === null) {
            return null;
        }

        return [
            'rate' => $price->rate,
            'name' => (string) $price->getAttribute('name'),
        ];
    }

    /**
     * @param  array<int, array{
     *     rate: float,
     *     exchange_item_trade_id: string,
     *     name: string,
     *     price_check_core_id: int,
     * }>  $coreTypesData
     */
    #[\Override]
    public function insertCoreTypes(array $coreTypesData): void
    {
        PriceCheckCoreType::query()->insert($coreTypesData);
    }

    #[\Override]
    public function findLatestCreatedAtByLeague(int $leagueId): ?string
    {
        $createdAt = PriceCheck::query()
            ->where('poe_league_id', $leagueId)
            ->max('created_at');

        return $createdAt === null ? null : (string) $createdAt;
    }

    #[\Override]
    public function findLatestIdByLeague(int $leagueId): ?int
    {
        $id = PriceCheck::query()
            ->where('poe_league_id', $leagueId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @return array<int, array{name: string, prices: array<int, float>}>
     */
    #[\Override]
    public function getPricesByCurrency(int $priceCheckId): array
    {
        $currencies = [];

        $rows = ExchangeItemPrice::query()
            ->join('price_check_cores', 'price_check_cores.id', '=', 'exchange_item_prices.price_check_core_id')
            ->join('price_checks', 'price_checks.id', '=', 'price_check_cores.price_check_id')
            ->join('price_check_core_types', 'price_check_core_types.price_check_core_id', '=', 'price_check_cores.id')
            ->join('exchange_items as currencies', static function (JoinClause $join): void {
                $join->on('currencies.trade_id', '=', 'price_check_core_types.exchange_item_trade_id')
                    ->on('currencies.poe_league_id', '=', 'price_checks.poe_league_id');
            })
            ->where('price_check_cores.price_check_id', $priceCheckId)
            ->where('exchange_item_prices.rate', '>', 0)
            ->where('price_check_core_types.rate', '>', 0)
            ->whereColumn('exchange_item_prices.exchange_item_id', '!=', 'currencies.id')
            ->select([
                'currencies.id as currency_item_id',
                'currencies.name as currency_name',
                DB::raw('exchange_item_prices.rate * price_check_core_types.rate as price'),
            ])
            ->cursor();

        foreach ($rows as $row) {
            $currencyItemId = (int) $row->getAttribute('currency_item_id');

            $currencies[$currencyItemId]['name'] = (string) $row->getAttribute('currency_name');
            $currencies[$currencyItemId]['prices'][] = (float) $row->getAttribute('price');
        }

        return $currencies;
    }

    /**
     * @return array<int, float>
     */
    #[\Override]
    public function getItemRates(int $priceCheckId, int $excludedExchangeItemId): array
    {
        return ExchangeItemPrice::query()
            ->join('price_check_cores', 'price_check_cores.id', '=', 'exchange_item_prices.price_check_core_id')
            ->where('price_check_cores.price_check_id', $priceCheckId)
            ->where('exchange_item_prices.rate', '>', 0)
            ->where('exchange_item_prices.exchange_item_id', '!=', $excludedExchangeItemId)
            ->pluck('exchange_item_prices.rate')
            ->map(static fn (mixed $rate): float => (float) $rate)
            ->all();
    }
}
