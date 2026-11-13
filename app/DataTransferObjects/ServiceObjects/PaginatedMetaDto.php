<?php

namespace App\DataTransferObjects\ServiceObjects;

final readonly class PaginatedMetaDto
{
    public function __construct(
        public int $total,
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
    ) {}

}
