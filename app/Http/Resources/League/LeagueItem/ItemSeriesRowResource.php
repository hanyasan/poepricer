<?php

namespace App\Http\Resources\League\LeagueItem;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemSeriesRowDto;
use App\Http\Resources\BaseResource;

final class ItemSeriesRowResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var ItemSeriesRowDto $series */
        $series = $this->resource;

        return [
            'bucket' => self::toDefaultResponseDatetimeBucket($series->bucket_start),
            'price' => $series->price,
            'residual' => $series->residual,
            'residualMad' => $series->residual_mad,
        ];
    }
}
