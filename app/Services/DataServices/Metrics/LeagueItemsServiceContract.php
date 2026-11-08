<?php

namespace App\Services\DataServices\Metrics;

use App\DataTransferObjects\ServiceObjects\LeagueItems\ItemsFilterDto;
use App\DataTransferObjects\ServiceObjects\LeagueItems\ItemsSortDto;
use App\DataTransferObjects\ServiceObjects\LeagueItems\LeagueItemCategoriesDto;
use App\DataTransferObjects\ServiceObjects\LeagueItems\LeagueItemsPageDto;
use App\DataTransferObjects\ServiceObjects\PaginationDto;
use App\Exceptions\League\LeagueNotFoundException;
use Illuminate\Validation\ValidationException;

interface LeagueItemsServiceContract
{
    /**
     * Categories of the items of the league, cached until the import changes them.
     *
     * @throws LeagueNotFoundException
     */
    public function categories(string $leagueSlug): LeagueItemCategoriesDto;

    /**
     * A page of the items table of the league measured in the currency of the
     * filter, the default main currency when it has none.
     *
     * @throws LeagueNotFoundException
     * @throws ValidationException when the league has no such metric currency
     */
    public function items(
        string $leagueSlug,
        ItemsFilterDto $filter,
        ItemsSortDto $sort,
        PaginationDto $pagination,
    ): LeagueItemsPageDto;
}
