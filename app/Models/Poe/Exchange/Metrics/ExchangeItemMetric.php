<?php

namespace App\Models\Poe\Exchange\Metrics;

use App\Models\Poe\Exchange\ExchangeItem;
use Database\Factories\Poe\Exchange\Metrics\ExchangeItemMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property string $bucket_start
 * @property float $price
 * @property float|null $log_return
 * @property float|null $residual
 * @property float|null $residual_mad
 * @property int $observations
 * @property float|null $units
 * @property float|null $return_weight
 * @property-read int $exchange_item_id
 * @property-read ExchangeItem $exchangeItem
 * @property-read int $exchange_metrics_item_id
 * @property-read ExchangeMetricsItem $exchangeMetricsItem
 */
final class ExchangeItemMetric extends Model
{
    /** @use HasFactory<ExchangeItemMetricFactory> */
    use HasFactory;

    protected $table = 'exchange_item_metrics';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function exchangeItem(): BelongsTo
    {
        return $this->belongsTo(ExchangeItem::class, 'exchange_item_id');
    }

    public function exchangeMetricsItem(): BelongsTo
    {
        return $this->belongsTo(ExchangeMetricsItem::class, 'exchange_metrics_item_id');
    }
}
