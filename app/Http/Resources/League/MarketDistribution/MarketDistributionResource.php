<?php

namespace App\Http\Resources\League\MarketDistribution;

use App\DataTransferObjects\ServiceObjects\MarketDistribution\MarketDistributionDto;
use App\Http\Resources\BaseResource;

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
            'bins' => MarketDistributionBinResource::collection($distribution->bins),
            'byCategory' => MarketDistributionCategoryResource::collection($distribution->byCategory),
            'items' => MarketDistributionItemResource::collection($distribution->items),
        ];
    }
}
