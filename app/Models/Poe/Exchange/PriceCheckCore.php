<?php

namespace App\Models\Poe\Exchange;

use App\Models\Poe\PoeLeague;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 *
 * @property string $created_at
 *
 * @property-read Collection<PriceCheckCoreType> $priceCheckCoreTypes
 * @property-read Collection<ExchangeItemPrice> $prices
 *
 * @property-read int $poe_league_id
 * @property-read PoeLeague $league
 */
final class PriceCheckCore extends Model
{
    protected $table = 'price_check_cores';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function league(): BelongsTo
    {
        return $this->belongsTo(PoeLeague::class, 'poe_league_id');
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
