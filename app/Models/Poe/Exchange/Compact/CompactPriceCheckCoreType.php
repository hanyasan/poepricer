<?php

namespace App\Models\Poe\Exchange\Compact;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property float $rate
 * @property string $exchange_item_trade_id
 * @property string $name
 * @property-read int $compact_price_check_core_id
 * @property-read CompactPriceCheckCore $compactPriceCheckCore
 */
final class CompactPriceCheckCoreType extends Model
{
    protected $table = 'compact_price_check_core_types';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function compactPriceCheckCore(): BelongsTo
    {
        return $this->belongsTo(CompactPriceCheckCore::class, 'compact_price_check_core_id');
    }
}
