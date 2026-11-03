<?php

namespace App\DataTransferObjects\RepositoryObjects\Exchange;

final readonly class PriceCheckDto
{
    public function __construct(
        public int $id,
        public string $created_at,
        public int $poe_league_id,
    ) {}
}
