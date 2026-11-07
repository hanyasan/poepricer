<?php

namespace App\Enums;

enum ExchangeMetricsSeedStatusEnum
{
    case Seeded;
    case AlreadySeeded;
    case NoPriceCheck;
    case NoPricedItems;
}
