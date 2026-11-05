<?php

namespace App\Repositories\Poe\Exchange\Compact;

use Carbon\Carbon;

interface ExchangeCompactRepositoryContract
{
    /**
     * Returns the price checks created on or before the given UTC day,
     * together with the UTC day each of them belongs to.
     *
     * @return array<int, array{
     *     id: int,
     *     poe_league_id: int,
     *     date: string,
     * }>
     */
    public function getLeagueDailyPeriodsPriceCheckIds(Carbon $date): array;

    /**
     * Averages the core types of the given price checks per exchange type and item.
     *
     * @param  array<int, int>  $priceCheckIds
     * @return array<int, array{
     *     exchange_type_id: int,
     *     exchange_item_trade_id: string,
     *     name: string,
     *     rate: float,
     * }>
     */
    public function getAvgCoreTypesData(array $priceCheckIds): array;

    /**
     * Averages the item prices of the given price checks per exchange type and item.
     *
     * @param  array<int, int>  $priceCheckIds
     * @return array<int, array{
     *     exchange_type_id: int,
     *     exchange_item_id: int,
     *     rate: float,
     * }>
     */
    public function getAvgCorePricesData(array $priceCheckIds): array;

    /**
     * Creates a compact price check for the league day in the compact tables:
     * one core per exchange type with the averaged types and prices.
     *
     * @param  array<int, array{
     *     exchange_type_id: int,
     *     exchange_item_trade_id: string,
     *     name: string,
     *     rate: float,
     * }>  $types
     * @param  array<int, array{
     *     exchange_type_id: int,
     *     exchange_item_id: int,
     *     rate: float,
     * }>  $prices
     *
     * @throws \InvalidArgumentException when the exchange type ids of $types and $prices differ
     */
    public function makeCompactPriceCheck(
        int $leagueId,
        string $date,
        array $types,
        array $prices,
    ): void;
}
