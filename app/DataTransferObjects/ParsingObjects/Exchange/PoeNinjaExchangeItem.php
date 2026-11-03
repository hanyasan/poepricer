<?php

namespace App\DataTransferObjects\ParsingObjects\Exchange;

final readonly class PoeNinjaExchangeItem
{
    public function __construct(
        public string $id,
        public string $name,
        public string $category,
    ) {}
}
