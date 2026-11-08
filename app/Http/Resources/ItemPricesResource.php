<?php

namespace App\Http\Resources;

use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheck\ItemPriceCurrencyRowDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheck\ItemPriceSnapshotRowDto;
use App\DataTransferObjects\ServiceObjects\ItemPrices\ItemPriceChangeDto;
use App\DataTransferObjects\ServiceObjects\ItemPrices\ItemPricesDto;

final class ItemPricesResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var ItemPricesDto $prices */
        $prices = $this->resource;

        return [
            'currency' => $prices->currency,
            'window' => $prices->window->value,
            'from' => self::toDefaultResponseDatetime($prices->from),
            'to' => self::toDefaultResponseDatetime($prices->to),
            'currencies' => array_map(static fn (ItemPriceCurrencyRowDto $currency): array => [
                'slug' => $currency->slug,
                'name' => $currency->name,
                'points' => $currency->points,
            ], $prices->currencies),
            'points' => array_map(self::point(...), $prices->points),
            'stats' => $prices->stats === null ? null : [
                'current' => self::point($prices->stats->current),
                'min' => $prices->stats->min,
                'max' => $prices->stats->max,
                'median' => $prices->stats->median,
                'averageUnits' => $prices->stats->averageUnits,
                'changes' => array_map(static fn (ItemPriceChangeDto $change): array => [
                    'hours' => $change->hours,
                    'change' => $change->change,
                ], $prices->stats->changes),
            ],
        ];
    }

    /**
     * @return array{at: string, price: float, units: float}
     */
    private static function point(ItemPriceSnapshotRowDto $point): array
    {
        return [
            'at' => self::toDefaultResponseDatetime($point->at),
            'price' => $point->price,
            'units' => $point->units,
        ];
    }
}
