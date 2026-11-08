<?php

namespace App\DataTransferObjects\ServiceObjects\LeagueItems;

final readonly class LeagueItemsPageDto
{
    /**
     * @param  LeagueItemRowDto[]  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public ?string $currency,
        public ?string $lastBucket,
    ) {}
}
