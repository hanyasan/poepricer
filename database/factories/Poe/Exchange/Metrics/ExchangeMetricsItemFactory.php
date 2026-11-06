<?php

namespace Database\Factories\Poe\Exchange\Metrics;

use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\Exchange\Metrics\ExchangeMetricsItem;
use App\Models\Poe\PoeLeague;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeMetricsItem>
 */
final class ExchangeMetricsItemFactory extends Factory
{
    protected $model = ExchangeMetricsItem::class;

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'min_price' => 0.0,
        ];
    }

    public function forLeagueCurrency(PoeLeague $league, ExchangeItem $currency): self
    {
        return $this->state([
            'poe_league_id' => $league->id,
            'exchange_item_id' => $currency->id,
        ]);
    }

    public function withMinPrice(float $minPrice): self
    {
        return $this->state(['min_price' => $minPrice]);
    }
}
