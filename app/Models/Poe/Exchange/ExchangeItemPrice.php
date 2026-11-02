<?php

namespace App\Models\Poe\Exchange;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property float $rate
 *
 * @property-read int $exchange_item_id
 * @property-read ExchangeItem $exchangeItem
 *
 * @property-read int $price_check_core_id
 * @property-read PriceCheckCore $priceCheckCore
 */
final class ExchangeItemPrice extends Model
{
    protected $table = 'exchange_item_prices';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function exchangeItem(): BelongsTo
    {
        return $this->belongsTo(ExchangeItem::class, 'exchange_item_id');
    }

    public function priceCheckCore(): BelongsTo
    {
        return $this->belongsTo(PriceCheckCore::class, 'price_check_core_id');
    }
}
