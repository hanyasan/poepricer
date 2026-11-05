<?php

namespace App\Models\Poe\Exchange\Compact;

use App\Models\Poe\Exchange\ExchangeItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property float $rate
 * @property-read int $exchange_item_id
 * @property-read ExchangeItem $exchangeItem
 * @property-read int $compact_price_check_core_id
 * @property-read CompactPriceCheckCore $compactPriceCheckCore
 */
final class CompactExchangeItemPrice extends Model
{
    protected $table = 'compact_exchange_item_prices';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function exchangeItem(): BelongsTo
    {
        return $this->belongsTo(ExchangeItem::class, 'exchange_item_id');
    }

    public function compactPriceCheckCore(): BelongsTo
    {
        return $this->belongsTo(CompactPriceCheckCore::class, 'compact_price_check_core_id');
    }
}
