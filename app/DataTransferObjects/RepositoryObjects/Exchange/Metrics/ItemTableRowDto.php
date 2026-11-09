<?php

namespace App\DataTransferObjects\RepositoryObjects\Exchange\Metrics;

final readonly class ItemTableRowDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $trade_id,
        public ?string $poe_category,
        public ?float $price,
        public ?float $log_return,
        public ?float $residual,
        public ?float $residual_mad,
        public ?int $observations,
        public ?float $units,
        public ?float $return_weight,
        public ?float $beta,
        public ?float $current_price = null,
    ) {}
}
