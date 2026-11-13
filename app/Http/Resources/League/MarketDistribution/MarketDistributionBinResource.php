<?php

namespace App\Http\Resources\League\MarketDistribution;

use App\DataTransferObjects\ServiceObjects\MarketDistribution\MarketBinDto;
use App\Http\Resources\BaseResource;

final class MarketDistributionBinResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var MarketBinDto $bin */
        $bin = $this->resource;

        return [
            'from' => $bin->from,
            'to' => $bin->to,
            'count' => $bin->count,
        ];
    }
}
