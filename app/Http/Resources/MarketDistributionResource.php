<?php

namespace App\Http\Resources;

use App\DataTransferObjects\ServiceObjects\MarketDistribution\MarketBinDto;
use App\DataTransferObjects\ServiceObjects\MarketDistribution\MarketCategoryDto;
use App\DataTransferObjects\ServiceObjects\MarketDistribution\MarketDistributionDto;
use App\DataTransferObjects\ServiceObjects\MarketDistribution\MarketItemDto;

final class MarketDistributionResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var MarketDistributionDto $distribution */
        $distribution = $this->resource;

        return [
            'currency' => $distribution->currency,
            'bucket' => self::toDefaultResponseDatetime($distribution->bucket),
            'buckets' => array_map(
                static fn (string $bucket): string => self::toDefaultResponseDatetime($bucket),
                $distribution->buckets,
            ),
            'marketReturn' => $distribution->marketReturn,
            'median' => $distribution->median,
            'breadth' => [
                'up' => $distribution->breadth->up,
                'down' => $distribution->breadth->down,
                'flat' => $distribution->breadth->flat,
            ],
            'bins' => array_map(static fn (MarketBinDto $bin): array => [
                'from' => $bin->from,
                'to' => $bin->to,
                'count' => $bin->count,
            ], $distribution->bins),
            'byCategory' => array_map(static fn (MarketCategoryDto $category): array => [
                'category' => $category->category,
                'median' => $category->median,
                'count' => $category->count,
            ], $distribution->byCategory),
            'items' => array_map(static fn (MarketItemDto $item): array => [
                'slug' => $item->slug,
                'name' => $item->name,
                'category' => $item->category,
                'price' => $item->price,
                'logReturn' => $item->logReturn,
                'units' => $item->units,
                'returnWeight' => $item->returnWeight,
            ], $distribution->items),
        ];
    }
}
