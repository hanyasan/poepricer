<?php

namespace App\DataTransferObjects\ServiceObjects\LeagueItems;

final readonly class LeagueItemCategoriesDto
{
    /**
     * @param  string[]  $categories
     */
    public function __construct(
        public array $categories,
        public bool $hasUncategorized,
    ) {}
}
