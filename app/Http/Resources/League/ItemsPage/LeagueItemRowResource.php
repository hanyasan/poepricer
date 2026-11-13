<?php

namespace App\Http\Resources\League\ItemsPage;

use App\DataTransferObjects\ServiceObjects\LeagueItems\LeagueItemRowDto;
use App\Http\Resources\BaseResource;

final class LeagueItemRowResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var LeagueItemRowDto $item */
        $item = $this->resource;

        return [
            'slug' => $item->slug,
            'name' => $item->name,
            'category' => $item->category,
            'price' => $item->price,
            'logReturn' => $item->logReturn,
            'residual' => $item->residual,
            'residualMad' => $item->residualMad,
            'beta' => $item->beta,
            'observations' => $item->observations,
            'units' => $item->units,
            'returnWeight' => $item->returnWeight,
            'lowData' => $item->lowData,
            'isUnusual' => $item->isUnusual,
            'sparkline' => ItemPricePointResource::collection($item->sparkline),
        ];
    }
}
