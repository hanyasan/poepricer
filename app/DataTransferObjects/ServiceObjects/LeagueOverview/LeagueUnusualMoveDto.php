<?php

namespace App\DataTransferObjects\ServiceObjects\LeagueOverview;

final readonly class LeagueUnusualMoveDto
{
    public function __construct(
        public string $slug,
        public string $name,
        public float $price,
        public float $logReturn,
        public float $residual,
        public float $residualMad,
        public float $ratio,
        public float $beta,
        public int $observations,
        public ?float $units,
        public ?float $returnWeight,
    ) {}
}
