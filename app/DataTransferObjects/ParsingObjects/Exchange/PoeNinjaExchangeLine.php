<?php

namespace App\DataTransferObjects\ParsingObjects\Exchange;

final readonly class PoeNinjaExchangeLine
{
    public function __construct(
        public string $id,
        public ?float $primaryValue = null,
    ) {}
}
