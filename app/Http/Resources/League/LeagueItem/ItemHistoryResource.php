<?php

namespace App\Http\Resources\League\LeagueItem;

use App\DataTransferObjects\ServiceObjects\ItemDetail\ItemHistoryDto;
use App\Http\Resources\BaseResource;

final class ItemHistoryResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var ItemHistoryDto $history */
        $history = $this->resource;

        return [
            'currency' => $history->currency,
            'period' => $history->period->value,
            'lastBucket' => self::toDefaultResponseDatetime($history->lastBucket),
            'series' => ItemSeriesRowResource::collection($history->series),
            'betaHistory' => ExchangeItemBetaResource::collection($history->betaHistory),
        ];
    }
}
