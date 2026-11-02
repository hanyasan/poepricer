<?php

namespace App\Models\Poe\Exchange;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property float $rate
 * @property string $exchange_item_trade_id
 * @property string $name
 *
 * @property-read int $price_check_core_id
 * @property-read PriceCheckCore $priceCheckCore
 */
final class PriceCheckCoreType extends Model
{
    protected $table = 'price_check_core_types';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function priceCheckCore(): BelongsTo
    {
        return $this->belongsTo(PriceCheckCore::class, 'price_check_core_id');
    }
}
