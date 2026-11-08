<?php

namespace App\DataTransferObjects\ServiceObjects\ItemPrices;

use App\Enums\ItemPriceWindowEnum;

final readonly class ItemPricesQueryDto
{
    public function __construct(
        public ?string $currency,
        public ItemPriceWindowEnum $window,
    ) {}
}
