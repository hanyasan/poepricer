<?php

namespace App\Http\Resources\League\MarketDistribution;

use App\DataTransferObjects\ServiceObjects\MarketDistribution\MarketItemDto;
use App\Http\Resources\BaseResource;

final class MarketDistributionItemResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var MarketItemDto $item */
        $item = $this->resource;

        return [
            'slug' => $item->slug,
            'name' => $item->name,
            'category' => $item->category,
            'price' => $item->price,
            'logReturn' => $item->logReturn,
            'units' => $item->units,
            'returnWeight' => $item->returnWeight,
        ];
    }
}
