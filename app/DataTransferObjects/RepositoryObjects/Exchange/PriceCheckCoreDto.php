<?php

namespace App\DataTransferObjects\RepositoryObjects\Exchange;

final readonly class PriceCheckCoreDto
{
    /**
     * @param  PriceCheckCoreTypeDto[]  $price_check_core_types
     */
    public function __construct(
        public int $id,
        public string $created_at,
        public int $poe_league_id,
        public array $price_check_core_types,
    ) {}
}
