<?php

namespace App\Http\Resources\League\LeagueItem;

use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheck\ItemPriceCurrencyRowDto;
use App\DataTransferObjects\ServiceObjects\ItemPrices\ItemPriceChangeDto;
use App\DataTransferObjects\ServiceObjects\ItemPrices\ItemPricesDto;
use App\Http\Resources\BaseResource;

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
            'points' => ExchangeItemPriceSnapshotResource::collection($prices->points),
            'stats' => $prices->stats === null ? null : [
                'current' => ExchangeItemPriceSnapshotResource::make($prices->stats->current),
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
}
