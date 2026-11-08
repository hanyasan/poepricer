<?php

namespace App\Repositories\Poe\Exchange\PriceCheck;

use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheck\ItemPriceCurrencyRowDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheck\ItemPriceSnapshotRowDto;
use App\Models\Poe\Exchange\ExchangeItemPrice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class ExchangeItemPriceHistoryRepository implements ExchangeItemPriceHistoryRepositoryContract
{
    /**
     * @return ItemPriceCurrencyRowDto[]
     */
    #[\Override]
    public function getCurrencies(
        int $leagueId,
        int $itemId,
        string $itemTradeId,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        return $this->itemPricedInCoreTypes($leagueId, $itemId, $from, $to)
            ->where('price_check_core_types.exchange_item_trade_id', '!=', $itemTradeId)
            ->groupBy('price_check_core_types.exchange_item_trade_id')
            ->orderByDesc('points')
            ->orderBy('price_check_core_types.exchange_item_trade_id')
            ->toBase()
            ->select([
                'price_check_core_types.exchange_item_trade_id as slug',
                DB::raw('min(price_check_core_types.name) as name'),
                DB::raw('count(distinct price_checks.id) as points'),
            ])
            ->get()
            ->map(static fn (object $row): ItemPriceCurrencyRowDto => new ItemPriceCurrencyRowDto(
                (string) $row->slug,
                (string) $row->name,
                (int) $row->points,
            ))
            ->all();
    }

    /**
     * @return ItemPriceSnapshotRowDto[]
     */
    #[\Override]
    public function getPoints(
        int $leagueId,
        int $itemId,
        string $currency,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        $rows = $this->itemPricedInCoreTypes($leagueId, $itemId, $from, $to)
            ->where('price_check_core_types.exchange_item_trade_id', $currency)
            ->orderBy('price_checks.created_at')
            ->orderBy('exchange_item_prices.id')
            ->toBase()
            ->select([
                'price_checks.id as price_check_id',
                'price_checks.created_at as at',
                DB::raw('exchange_item_prices.rate * price_check_core_types.rate as price'),
                DB::raw('coalesce(exchange_item_prices.volume / exchange_item_prices.rate, 0) as units'),
            ])
            ->get();

        $points = [];

        foreach ($rows as $row) {
            $points[(int) $row->price_check_id] ??= new ItemPriceSnapshotRowDto(
                (string) $row->at,
                (float) $row->price,
                (float) $row->units,
            );
        }

        return array_values($points);
    }

    /**
     * Prices of the item in the window joined with the core types of the same core.
     *
     * @return Builder<ExchangeItemPrice>
     */
    private function itemPricedInCoreTypes(int $leagueId, int $itemId, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return ExchangeItemPrice::query()
            ->join('price_check_cores', 'price_check_cores.id', '=', 'exchange_item_prices.price_check_core_id')
            ->join('price_checks', 'price_checks.id', '=', 'price_check_cores.price_check_id')
            ->join('price_check_core_types', 'price_check_core_types.price_check_core_id', '=', 'price_check_cores.id')
            ->where('exchange_item_prices.exchange_item_id', $itemId)
            ->where('price_checks.poe_league_id', $leagueId)
            ->whereBetween('price_checks.created_at', [self::timestamp($from), self::timestamp($to)])
            ->where('exchange_item_prices.rate', '>', 0)
            ->where('price_check_core_types.rate', '>', 0);
    }

    private static function timestamp(CarbonImmutable $moment): string
    {
        return $moment->utc()->format('Y-m-d H:i:sP');
    }
}
