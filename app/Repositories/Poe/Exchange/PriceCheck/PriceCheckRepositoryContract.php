<?php

namespace App\Repositories\Poe\Exchange\PriceCheck;

use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheckCoreDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheckDto;

interface PriceCheckRepositoryContract
{
    public function deleteMany(array $ids): void;

    public function createPriceCheck(int $leagueId): PriceCheckDto;

    /**
     * @param  array<int, array{
     *      rate: float,
     *      exchange_item_trade_id: string,
     *      name: string,
     * }>  $types
     */
    public function createCore(
        int $priceCheckId,
        int $exchangeTypeId,
        array $types,
    ): PriceCheckCoreDto;

    /**
     * @param  array<int, array{
     *     rate: float,
     *     price_check_core_id: int,
     *     exchange_item_id: int,
     * }>  $pricesData
     */
    public function insertPrices(array $pricesData): void;
}
