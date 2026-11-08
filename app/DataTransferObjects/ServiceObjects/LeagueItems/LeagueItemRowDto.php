<?php

namespace App\DataTransferObjects\ServiceObjects\LeagueItems;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ItemPricePointDto;

final readonly class LeagueItemRowDto
{
    /**
     * @param  ItemPricePointDto[]  $sparkline
     */
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $category,
        public ?float $price,
        public ?float $logReturn,
        public ?float $residual,
        public ?float $residualMad,
        public ?float $beta,
        public int $observations,
        public ?float $units,
        public ?float $returnWeight,
        public bool $lowData,
        public bool $isUnusual,
        public array $sparkline,
    ) {}
}
