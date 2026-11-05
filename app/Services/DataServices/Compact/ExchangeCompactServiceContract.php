<?php

namespace App\Services\DataServices\Compact;

interface ExchangeCompactServiceContract {
    public function compactPricesByBaseTimeRule(): void;
}
