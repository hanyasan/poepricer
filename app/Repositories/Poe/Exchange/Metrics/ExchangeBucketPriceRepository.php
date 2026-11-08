<?php

namespace App\Repositories\Poe\Exchange\Metrics;

use App\Models\Poe\Exchange\ExchangeItemPrice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final readonly class ExchangeBucketPriceRepository implements ExchangeBucketPriceRepositoryContract
{
    /**
     * The stored rate is expressed in the core's primary currency, so it is
     * multiplied by the core's rate of the requested currency. Units are the items
     * traded per hour (volume divided by the item rate), null when the volume is unknown.
     *
     * @return array<int, list<array{price: float, units: float|null}>>
     */
    #[\Override]
    public function getPricesByItem(
        int $leagueId,
        string $currencyTradeId,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        $prices = [];

        $rows = ExchangeItemPrice::query()
            ->join('price_check_cores', 'price_check_cores.id', '=', 'exchange_item_prices.price_check_core_id')
            ->join('price_checks', 'price_checks.id', '=', 'price_check_cores.price_check_id')
            ->join('price_check_core_types', static function (JoinClause $join) use ($currencyTradeId): void {
                $join->on('price_check_core_types.price_check_core_id', '=', 'price_check_cores.id')
                    ->where('price_check_core_types.exchange_item_trade_id', '=', $currencyTradeId);
            })
            ->where('price_checks.poe_league_id', $leagueId)
            ->where('price_checks.created_at', '>=', $from->utc()->format('Y-m-d H:i:sP'))
            ->where('price_checks.created_at', '<', $to->utc()->format('Y-m-d H:i:sP'))
            ->where('exchange_item_prices.rate', '>', 0)
            ->where('price_check_core_types.rate', '>', 0)
            ->select([
                'exchange_item_prices.exchange_item_id',
                DB::raw('exchange_item_prices.rate * price_check_core_types.rate as price'),
                DB::raw('exchange_item_prices.volume / exchange_item_prices.rate as units'),
            ])
            ->cursor();

        foreach ($rows as $row) {
            $units = $row->getAttribute('units');

            $prices[$row->exchange_item_id][] = [
                'price' => (float) $row->getAttribute('price'),
                'units' => $units === null ? null : (float) $units,
            ];
        }

        return $prices;
    }
}
