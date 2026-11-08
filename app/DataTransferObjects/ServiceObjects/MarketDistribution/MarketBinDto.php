<?php

namespace App\DataTransferObjects\ServiceObjects\MarketDistribution;

/**
 * A bar of the return histogram: `from` is null for the lower tail, `to` is null for the upper tail.
 */
final readonly class MarketBinDto
{
    public function __construct(
        public ?float $from,
        public ?float $to,
        public int $count,
    ) {}
}
