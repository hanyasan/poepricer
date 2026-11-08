<?php

namespace App\DataTransferObjects\RepositoryObjects\Exchange\Metrics;

final readonly class ExchangeItemBetaDto
{
    public function __construct(
        public string $calculated_at,
        public float $beta,
        public float $raw_beta,
        public ?float $r_squared,
        public int $observations,
    ) {}
}
