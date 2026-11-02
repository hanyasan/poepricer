<?php

namespace App\DataTransferObjects\ParsingObjects\Exchange;

final readonly class PoeNinjaExchangeResponse
{
    public function __construct(
        public PoeNinjaExchangeCore $core,
        /** @var PoeNinjaExchangeLine[] */
        public array                $lines,
        /** @var PoeNinjaExchangeItem[] */
        public array                $items,
    ) {}
}
