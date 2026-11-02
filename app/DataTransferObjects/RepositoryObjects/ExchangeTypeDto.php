<?php

namespace App\DataTransferObjects\RepositoryObjects;

final readonly class ExchangeTypeDto
{
    public function __construct(
        public int $id,
        public string $category,
        public string $value,
        public int $poe_version_id,
    ) {}
}
