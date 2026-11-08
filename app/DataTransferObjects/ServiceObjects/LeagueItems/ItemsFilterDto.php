<?php

namespace App\DataTransferObjects\ServiceObjects\LeagueItems;

final readonly class ItemsFilterDto
{
    public function __construct(
        public ?string $currency = null,
        public ?string $search = null,
        public ?string $category = null,
        public bool $uncategorized = false,
    ) {}
}
