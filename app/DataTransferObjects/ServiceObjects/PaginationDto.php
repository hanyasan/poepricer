<?php

namespace App\DataTransferObjects\ServiceObjects;

final readonly class PaginationDto
{
    public function __construct(
        public int $page,
        public int $perPage,
    ) {}

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
