<?php

namespace Database\Factories\Poe\Exchange\Metrics;

use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\Exchange\Metrics\ExchangeItemBeta;
use App\Models\Poe\Exchange\Metrics\ExchangeMetricsItem;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeItemBeta>
 */
final class ExchangeItemBetaFactory extends Factory
{
    protected $model = ExchangeItemBeta::class;

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'calculated_at' => '2030-01-01 00:00:00',
            'beta' => 1.0,
            'raw_beta' => 1.0,
            'r_squared' => 0.5,
            'observations' => 14,
        ];
    }

    public function forItem(ExchangeItem $item, ExchangeMetricsItem $metricsItem): self
    {
        return $this->state([
            'exchange_item_id' => $item->id,
            'exchange_metrics_item_id' => $metricsItem->id,
        ]);
    }

    public function calculatedAt(CarbonInterface|string $calculatedAt): self
    {
        return $this->state(['calculated_at' => $calculatedAt]);
    }

    public function withBeta(float $beta): self
    {
        return $this->state(['beta' => $beta, 'raw_beta' => $beta]);
    }
}
