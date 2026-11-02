<?php

namespace App\DataTransferObjects\ParsingObjects\Exchange;

final readonly class PoeNinjaExchangeCore
{
    public function __construct(
        /** @var PoeNinjaExchangeItem[] */
        public array  $items,
        /** @var array<string, float> */
        public array  $rates,
    ) {}
}
