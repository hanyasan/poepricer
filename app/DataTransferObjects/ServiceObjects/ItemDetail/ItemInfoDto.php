<?php

namespace App\DataTransferObjects\ServiceObjects\ItemDetail;

final readonly class ItemInfoDto
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $category,
    ) {}
}
