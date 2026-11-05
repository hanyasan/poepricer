<?php

namespace App\Models\Poe\Exchange\Compact;

use App\Models\Poe\Exchange\ExchangeType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property-read int $compact_price_check_id
 * @property-read CompactPriceCheck $compactPriceCheck
 * @property-read int $exchange_type_id
 * @property-read ExchangeType $exchangeType
 * @property-read Collection<int, CompactPriceCheckCoreType> $types
 * @property-read Collection<int, CompactExchangeItemPrice> $prices
 */
final class CompactPriceCheckCore extends Model
{
    protected $table = 'compact_price_check_cores';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function compactPriceCheck(): BelongsTo
    {
        return $this->belongsTo(CompactPriceCheck::class, 'compact_price_check_id');
    }

    public function exchangeType(): BelongsTo
    {
        return $this->belongsTo(ExchangeType::class, 'exchange_type_id');
    }

    public function types(): HasMany
    {
        return $this->hasMany(CompactPriceCheckCoreType::class, 'compact_price_check_core_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(CompactExchangeItemPrice::class, 'compact_price_check_core_id');
    }
}
