<?php

namespace App\Models\Poe\Exchange\Metrics;

use App\Models\Poe\PoeLeague;
use Database\Factories\Poe\Exchange\Metrics\ExchangeMarketMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property string $bucket_start
 * @property float $market_return
 * @property int $basket_size
 * @property int $basket_total
 * @property-read int $poe_league_id
 * @property-read PoeLeague $league
 * @property-read int $exchange_metrics_item_id
 * @property-read ExchangeMetricsItem $exchangeMetricsItem
 */
final class ExchangeMarketMetric extends Model
{
    /** @use HasFactory<ExchangeMarketMetricFactory> */
    use HasFactory;

    protected $table = 'exchange_market_metrics';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function league(): BelongsTo
    {
        return $this->belongsTo(PoeLeague::class, 'poe_league_id');
    }

    public function exchangeMetricsItem(): BelongsTo
    {
        return $this->belongsTo(ExchangeMetricsItem::class, 'exchange_metrics_item_id');
    }
}
