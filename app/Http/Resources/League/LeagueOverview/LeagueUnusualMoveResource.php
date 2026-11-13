<?php

namespace App\Http\Resources\League\LeagueOverview;

use App\DataTransferObjects\ServiceObjects\LeagueOverview\LeagueUnusualMoveDto;
use App\Http\Resources\BaseResource;

final class LeagueUnusualMoveResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var LeagueUnusualMoveDto $move */
        $move = $this->resource;

        return [
            'slug' => $move->slug,
            'name' => $move->name,
            'price' => $move->price,
            'logReturn' => $move->logReturn,
            'residual' => $move->residual,
            'residualMad' => $move->residualMad,
            'ratio' => $move->ratio,
            'beta' => $move->beta,
            'observations' => $move->observations,
            'units' => $move->units,
            'returnWeight' => $move->returnWeight,
        ];
    }
}
