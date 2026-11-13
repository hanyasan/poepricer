<?php

namespace App\Http\Resources\League\LeagueOverview;

use App\DataTransferObjects\ServiceObjects\LeagueOverview\LeagueItemMoveDto;
use App\Http\Resources\BaseResource;

final class LeagueItemMoveResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var LeagueItemMoveDto $move */
        $move = $this->resource;

        return [
            'slug' => $move->slug,
            'name' => $move->name,
            'price' => $move->price,
            'logReturn' => $move->logReturn,
        ];
    }
}
