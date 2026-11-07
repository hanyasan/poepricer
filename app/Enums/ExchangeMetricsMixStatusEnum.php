<?php

namespace App\Enums;

enum ExchangeMetricsMixStatusEnum
{
    case Added;
    case AlreadyExists;
    case ItemNotFound;
    case NoPriceCheck;
    case NoItemPrice;
    case NoPricedItems;
}
