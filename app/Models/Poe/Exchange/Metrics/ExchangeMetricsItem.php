<?php

namespace App\Models\Poe\Exchange\Metrics;

use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\PoeLeague;
use Database\Factories\Poe\Exchange\Metrics\ExchangeMetricsItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property float $min_price
 * @property-read int $poe_league_id
 * @property-read PoeLeague $league
 * @property-read int $exchange_item_id
 * @property-read ExchangeItem $exchangeItem
 */
final class ExchangeMetricsItem extends Model
{
    /** @use HasFactory<ExchangeMetricsItemFactory> */
    use HasFactory;

    protected $table = 'exchange_metrics_items';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function league(): BelongsTo
    {
        return $this->belongsTo(PoeLeague::class, 'poe_league_id');
    }

    public function exchangeItem(): BelongsTo
    {
        return $this->belongsTo(ExchangeItem::class, 'exchange_item_id');
    }
}
