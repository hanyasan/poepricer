<?php

namespace App\DataTransferObjects\RepositoryObjects\Exchange;

final readonly class ExchangeItemDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $trade_id,
        public int $poe_league_id,
    ) {}
}
