<?php

namespace App\DataTransferObjects\ServiceObjects\ItemDetail;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeItemBetaDto;

final readonly class ItemSummaryDto
{
    public function __construct(
        public ItemInfoDto $item,
        public ?string $currency,
        public ?string $lastBucket,
        public ?ItemCurrentDto $current,
        public ?ExchangeItemBetaDto $beta,
        public ?float $currentPrice = null,
        public ?string $currentPriceAt = null,
    ) {}
}
