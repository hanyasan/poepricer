<?php

namespace App\DataTransferObjects\ServiceObjects\LeagueItems;

use App\DataTransferObjects\ServiceObjects\PaginatedMetaDto;

final readonly class LeagueItemsPageDto
{
    /**
     * @param  LeagueItemRowDto[]  $items
     */
    public function __construct(
        public array $items,
        public PaginatedMetaDto $meta,
        public ?string $currency,
        public ?string $lastBucket,
        public ?string $lastSnapshotAt = null,
    ) {}
}
