<?php

namespace App\DataTransferObjects\RepositoryObjects;

/**
 * @template T of object
 */
final readonly class PaginatedResultDto
{
    /**
     * @param  T[]  $items  the items of the requested page
     * @param  int  $total  the number of items on all pages
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {}
}
