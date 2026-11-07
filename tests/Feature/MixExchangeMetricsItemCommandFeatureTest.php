<?php

namespace Tests\Feature;

use App\Models\Poe\Exchange\Metrics\ExchangeMetricsItem;
use Tests\Concerns\BuildsExchangeMetricsData;
use Tests\FeatureTestCase;

class MixExchangeMetricsItemCommandFeatureTest extends FeatureTestCase
{
    use BuildsExchangeMetricsData;

    public function test_it_adds_the_currency_to_the_metrics_items(): void
    {
        $league = $this->league('Command Mix League');
        $this->item($league, 'divine');
        $this->item($league, 'mirror');
        $this->item($league, 'item-a');
        $this->snapshot($league, $this->exchangeType(), '2026-11-01 00:00:00+00', ['mirror' => 100.0, 'item-a' => 10.0]);

        $this->artisan('poe:metrics:mix-item', ['league' => (string) $league->id, 'trade_id' => 'mirror'])
            ->expectsOutputToContain('Added [MIRROR]')
            ->assertSuccessful();

        $this->assertSame(1, ExchangeMetricsItem::query()->where('poe_league_id', $league->id)->count());
    }

    public function test_it_succeeds_without_changes_when_the_currency_is_already_added(): void
    {
        $league = $this->league('Added Command League');
        $this->metricsItem($league, $this->item($league, 'mirror'), 5.0);

        $this->artisan('poe:metrics:mix-item', ['league' => (string) $league->id, 'trade_id' => 'mirror'])
            ->expectsOutputToContain('already a metrics item')
            ->assertSuccessful();

        $this->assertSame(1, ExchangeMetricsItem::query()->where('poe_league_id', $league->id)->count());
    }

    public function test_it_fails_when_the_trade_id_is_unknown_in_the_league(): void
    {
        $league = $this->league('Unknown Item Command League');

        $this->artisan('poe:metrics:mix-item', ['league' => (string) $league->id, 'trade_id' => 'mirror'])
            ->expectsOutputToContain('has no exchange item with trade id [mirror]')
            ->assertFailed();
    }

    public function test_it_fails_when_the_league_has_no_price_checks(): void
    {
        $league = $this->league('No Price Check Command League');
        $this->item($league, 'mirror');

        $this->artisan('poe:metrics:mix-item', ['league' => (string) $league->id, 'trade_id' => 'mirror'])
            ->expectsOutputToContain('has no price checks')
            ->assertFailed();
    }

    public function test_it_fails_when_the_league_does_not_exist(): void
    {
        $this->artisan('poe:metrics:mix-item', ['league' => '999999', 'trade_id' => 'mirror'])
            ->expectsOutputToContain('does not exist')
            ->assertFailed();
    }

    public function test_it_fails_when_the_league_is_not_a_numeric_id(): void
    {
        $this->artisan('poe:metrics:mix-item', ['league' => 'Settlers', 'trade_id' => 'mirror'])
            ->expectsOutputToContain('must be a numeric id')
            ->assertFailed();
    }
}
