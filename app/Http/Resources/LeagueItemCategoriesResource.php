<?php

namespace App\Http\Resources;

use App\DataTransferObjects\ServiceObjects\LeagueItems\LeagueItemCategoriesDto;

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
