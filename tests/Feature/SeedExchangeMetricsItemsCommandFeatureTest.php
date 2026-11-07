<?php

namespace Tests\Feature;

use App\Models\Poe\Exchange\Metrics\ExchangeMetricsItem;
use Tests\Concerns\BuildsExchangeMetricsData;
use Tests\FeatureTestCase;

class SeedExchangeMetricsItemsCommandFeatureTest extends FeatureTestCase
{
    use BuildsExchangeMetricsData;

    public function test_it_seeds_the_metrics_items_of_the_league(): void
    {
        $league = $this->league('Command Seed League');
        $this->item($league, 'divine');
        $this->item($league, 'item-a');
        $this->snapshot($league, $this->exchangeType(), '2026-11-01 00:00:00+00', ['item-a' => 10.0]);

        $this->artisan('poe:metrics:seed-items', ['league' => (string) $league->id])
            ->expectsOutputToContain('Seeded 1 metrics items')
            ->assertSuccessful();

        $this->assertSame(1, ExchangeMetricsItem::query()->where('poe_league_id', $league->id)->count());
    }

    public function test_it_succeeds_without_changes_when_the_league_already_has_items(): void
    {
        $league = $this->league('Seeded Command League');
        $this->metricsItem($league, $this->item($league, 'divine'), 3.0);

        $this->artisan('poe:metrics:seed-items', ['league' => (string) $league->id])
            ->expectsOutputToContain('already has metrics items')
            ->assertSuccessful();

        $this->assertSame(1, ExchangeMetricsItem::query()->where('poe_league_id', $league->id)->count());
    }

    public function test_it_fails_when_the_league_has_no_price_checks(): void
    {
        $league = $this->league('Empty Command League');

        $this->artisan('poe:metrics:seed-items', ['league' => (string) $league->id])
            ->expectsOutputToContain('has no price checks')
            ->assertFailed();
    }

    public function test_it_fails_when_the_league_does_not_exist(): void
    {
        $this->artisan('poe:metrics:seed-items', ['league' => '999999'])
            ->expectsOutputToContain('does not exist')
            ->assertFailed();
    }

    public function test_it_fails_when_the_league_is_not_a_numeric_id(): void
    {
        $this->artisan('poe:metrics:seed-items', ['league' => 'Settlers'])
            ->expectsOutputToContain('must be a numeric id')
            ->assertFailed();
    }
}
