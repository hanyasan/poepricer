<?php

namespace App\Http\Resources\League\LeagueItem;

use App\DataTransferObjects\ServiceObjects\ItemDetail\ItemBucketsPageDto;
use App\Http\Resources\BaseResource;
use App\Http\Resources\MetaPaginationResource;

final class ItemBucketsPageResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var ItemBucketsPageDto $page */
        $page = $this->resource;

        return [
            'currency' => $page->currency,
            'period' => $page->period->value,
            'data' => ExchangeItemMetricResource::collection($page->items),
            'meta' => MetaPaginationResource::make($page->meta),
        ];
    }
}
