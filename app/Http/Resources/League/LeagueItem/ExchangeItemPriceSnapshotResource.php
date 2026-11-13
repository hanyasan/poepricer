<?php

namespace App\Http\Resources\League\LeagueItem;

use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheck\ItemPriceSnapshotRowDto;
use App\Http\Resources\BaseResource;

final class ExchangeItemPriceSnapshotResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var ItemPriceSnapshotRowDto $point */
        $point = $this->resource;

        return [
            'at' => self::toDefaultResponseDatetime($point->at),
            'price' => $point->price,
            'units' => $point->units,
        ];
    }
}
