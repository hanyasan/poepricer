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
     *     volume?: float|null,
     *     price_check_core_id: int,
     *     exchange_item_id: int,
     * }>  $pricesData
     */
    public function insertPrices(array $pricesData): void;

    /**
     * Ids of the cores of the price check that have no core type with the trade id.
     *
     * @return array<int, int>
     */
    public function getCoreIdsWithoutCoreType(int $priceCheckId, string $tradeId): array;

    /**
     * The price of an item in the primary currency of its core, found in any core of
     * the price check, together with the item name. Null when the item is not priced.
     *
     * @return array{rate: float, name: string}|null
     */
    public function findItemPrimaryPrice(int $priceCheckId, string $tradeId): ?array;

    /**
     * @param  array<int, array{
     *     rate: float,
     *     exchange_item_trade_id: string,
     *     name: string,
     *     price_check_core_id: int,
     * }>  $coreTypesData
     */
    public function insertCoreTypes(array $coreTypesData): void;

    public function findLatestIdByLeague(int $leagueId): ?int;

    /**
     * Prices of the items of the price check measured in each currency of the core
     * types, keyed by the exchange item id of the currency. The currency itself and
     * items with a non-positive price or core rate are left out.
     *
     * @return array<int, array{name: string, prices: array<int, float>}>
     */
    public function getPricesByCurrency(int $priceCheckId): array;
}
