<?php

namespace Database\Factories\Poe\Exchange\Metrics;

use App\Models\Poe\Exchange\Metrics\ExchangeMarketMetric;
use App\Models\Poe\Exchange\Metrics\ExchangeMetricsItem;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeMarketMetric>
 */
final class ExchangeMarketMetricFactory extends Factory
{
    protected $model = ExchangeMarketMetric::class;

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'bucket_start' => '2030-01-01 00:00:00',
            'market_return' => 0.0,
            'basket_size' => 1,
            'basket_total' => static fn (array $attributes): mixed => $attributes['basket_size'],
        ];
    }

    public function forMetricsItem(ExchangeMetricsItem $metricsItem): self
    {
        return $this->state([
            'poe_league_id' => $metricsItem->poe_league_id,
            'exchange_metrics_item_id' => $metricsItem->id,
        ]);
    }

    public function atBucket(CarbonInterface|string $bucketStart): self
    {
        return $this->state(['bucket_start' => $bucketStart]);
    }

    public function withReturn(float $marketReturn): self
    {
        return $this->state(['market_return' => $marketReturn]);
    }
}
