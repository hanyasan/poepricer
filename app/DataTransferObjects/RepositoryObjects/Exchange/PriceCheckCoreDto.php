<?php

namespace App\DataTransferObjects\RepositoryObjects\Exchange;

final readonly class PriceCheckCoreDto
{
    /**
     * @param  PriceCheckCoreTypeDto[]  $price_check_core_types
     */
    public function __construct(
        public int $id,
        public int $price_check_id,
        public int $exchange_type_id,
        public array $price_check_core_types,
    ) {}
}
