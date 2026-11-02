<?php

namespace App\Repositories\Poe\Exchange\ExchangeItemPrice;

use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\Exchange\ExchangeItemPrice as ExchangeItemPriceModel;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final readonly class ExchangeItemPriceRepository implements ExchangeItemPriceRepositoryContract
{
    public function __construct(
        private ExchangeItemPriceModel $model,
    ) {}

    /**
     * @param  array<int, array{
     *     rate: float,
     *     price_check_core_id: int,
     *     exchange_item_id: int,
     * }>  $pricesData
     */
    #[\Override]
    public function insert(array $pricesData): void
    {
        $this->model
            ->newQuery()
            ->insert(array_map(static function (array $priceData) {
                return [
                    'rate' => $priceData['rate'],
                    'exchange_item_id' => $priceData['exchange_item_id'],
                    'price_check_core_id' => $priceData['price_check_core_id'],
                ];
            }, $pricesData));
    }

    /**
     * @param  array<int, int>  $coreIds
     * @return array<int, array{
     *     price_avg_rate: float,
     *     name: string,
     *     trade_id: string,
     * }>
     */
    #[\Override]
    public function getAvgCorePricesData(array $coreIds): array
    {
        $avgPrices = ExchangeItemPriceModel::query()
            ->select([
                'exchange_item_id',
                DB::raw('AVG(rate) as price_avg_rate'),
            ])
            ->whereIn('price_check_core_id', $coreIds)
            ->groupBy('exchange_item_id');

        return ExchangeItem::query()
            ->select([
                'price_avg_rate',
                'exchange_items.name',
                'exchange_items.trade_id',
            ])
            ->rightJoinSub($avgPrices, 'avg_prices', function (JoinClause $join) {
                $join->on('avg_prices.exchange_item_id', '=', 'exchange_items.id');
            })
            ->get()
            ->toBase()
            ->toArray();
    }
}
