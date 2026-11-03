<?php

namespace App\Models\Poe\Exchange;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property-read Collection<PriceCheckCoreType> $priceCheckCoreTypes
 * @property-read Collection<ExchangeItemPrice> $prices
 * @property-read int $price_check_id
 * @property-read PriceCheck $priceCheck
 * @property-read int $exchange_type_id
 * @property-read ExchangeType $exchangeType
 */
final class PriceCheckCore extends Model
{
    protected $table = 'price_check_cores';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function priceCheck(): BelongsTo
    {
        return $this->belongsTo(PriceCheck::class, 'price_check_id');
    }

    public function exchangeType(): BelongsTo
    {
        return $this->belongsTo(ExchangeType::class, 'exchange_type_id');
    }

    public function priceCheckCoreTypes(): HasMany
    {
        return $this->hasMany(PriceCheckCoreType::class, 'price_check_core_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ExchangeItemPrice::class, 'price_check_core_id');
    }
}
