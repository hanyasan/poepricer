<?php

namespace App\Models\Poe\Exchange\Metrics;

use App\Models\Poe\Exchange\ExchangeItem;
use Database\Factories\Poe\Exchange\Metrics\ExchangeItemBetaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property string $calculated_at
 * @property float $beta
 * @property float $raw_beta
 * @property float|null $r_squared
 * @property int $observations
 * @property-read int $exchange_item_id
 * @property-read ExchangeItem $exchangeItem
 * @property-read int $exchange_metrics_item_id
 * @property-read ExchangeMetricsItem $exchangeMetricsItem
 */
final class ExchangeItemBeta extends Model
{
    /** @use HasFactory<ExchangeItemBetaFactory> */
    use HasFactory;

    protected $table = 'exchange_item_betas';

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
