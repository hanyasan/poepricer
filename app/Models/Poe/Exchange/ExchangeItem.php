<?php

namespace App\Models\Poe\Exchange;

use App\Models\Poe\PoeLeague;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property string $name
 * @property string $trade_id
 *
 * @property-read int $poe_league_id
 * @property-read PoeLeague $league
 */
final class ExchangeItem extends Model
{
    protected $table = 'exchange_items';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function league(): BelongsTo
    {
        return $this->belongsTo(PoeLeague::class, 'poe_league_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ExchangeItemPrice::class, 'exchange_item_id');
    }
}
