<?php

namespace App\Http\Resources\League\MarketDistribution;

use App\DataTransferObjects\ServiceObjects\MarketDistribution\MarketDistributionCategoryDto;
use App\Http\Resources\BaseResource;

final class MarketDistributionCategoryResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var MarketDistributionCategoryDto $category */
        $category = $this->resource;

        return [
            'category' => $category->category,
            'median' => $category->median,
            'count' => $category->count,
        ];
    }
}
