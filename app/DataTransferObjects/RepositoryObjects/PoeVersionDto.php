<?php

namespace App\DataTransferObjects\RepositoryObjects;

final readonly class PoeVersionDto
{
    public function __construct(
        public int $id,
        public string $version,
    ) {}
}
