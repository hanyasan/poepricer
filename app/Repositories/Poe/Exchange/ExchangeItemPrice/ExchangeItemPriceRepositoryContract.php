<?php

namespace App\Repositories\Poe\Exchange\ExchangeItemPrice;

interface ExchangeItemPriceRepositoryContract
{
    /**
     * @param  array<int, array{
     *     rate: float,
     *     price_check_core_id: int,
     *     exchange_item_id: int,
     * }>  $pricesData
     */
    public function insert(array $pricesData): void;

    /**
     * @param  array<int, int>  $coreIds
     * @return array<int, array{
     *     price_avg_rate: float,
     *     name: string,
     *     trade_id: string,
     * }>
     */
    public function getAvgCorePricesData(array $coreIds): array;
}
