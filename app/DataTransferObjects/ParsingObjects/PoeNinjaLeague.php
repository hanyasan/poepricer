<?php

namespace App\DataTransferObjects\ParsingObjects;

final readonly class PoeNinjaLeague
{
    public function __construct(
        public string $name,
        public string $id,
    ) {}
}
