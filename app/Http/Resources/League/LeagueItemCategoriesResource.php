<?php

namespace App\Http\Resources\League;

use App\DataTransferObjects\ServiceObjects\LeagueItems\LeagueItemCategoriesDto;
use App\Http\Resources\BaseResource;

final class LeagueItemCategoriesResource extends BaseResource
{
    #[\Override]
    public function toArray($request): array
    {
        /** @var LeagueItemCategoriesDto $categories */
        $categories = $this->resource;

        return [
            'categories' => $categories->categories,
            'hasUncategorized' => $categories->hasUncategorized,
        ];
    }
}
