<?php

namespace Database\Factories\Poe\Exchange\Metrics;

use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\Exchange\Metrics\ExchangeItemMetric;
use App\Models\Poe\Exchange\Metrics\ExchangeMetricsItem;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeItemMetric>
 */
final class ExchangeItemMetricFactory extends Factory
{
    protected $model = ExchangeItemMetric::class;

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'bucket_start' => '2030-01-01 00:00:00',
            'price' => 1.0,
            'log_return' => null,
            'residual' => null,
            'residual_mad' => null,
            'observations' => 0,
        ];
    }

    public function forItem(ExchangeItem $item, ExchangeMetricsItem $metricsItem): self
    {
        return $this->state([
            'exchange_item_id' => $item->id,
            'exchange_metrics_item_id' => $metricsItem->id,
        ]);
    }

    public function atBucket(CarbonInterface|string $bucketStart): self
    {
        return $this->state(['bucket_start' => $bucketStart]);
    }

    public function withReturn(float $logReturn, ?float $residual = null): self
    {
        return $this->state([
            'log_return' => $logReturn,
            'residual' => $residual ?? $logReturn,
        ]);
    }
}
