<?php

namespace Tests\Feature\Services\DataServices\Metrics;

use App\DataTransferObjects\ServiceObjects\ExchangeMetricsMixResultDto;
use App\DataTransferObjects\ServiceObjects\ExchangeMetricsSeedResultDto;
use App\Enums\ExchangeMetricsMixStatusEnum;
use App\Enums\ExchangeMetricsSeedStatusEnum;
use App\Models\Poe\Exchange\ExchangeType;
use App\Models\Poe\Exchange\Metrics\ExchangeMetricsItem;
use App\Models\Poe\PoeLeague;
use App\Services\DataServices\Metrics\ExchangeMetricsItemSeedServiceContract;
use Tests\Concerns\BuildsExchangeMetricsData;
use Tests\FeatureTestCase;

class ExchangeMetricsItemSeedServiceTest extends FeatureTestCase
{
    use BuildsExchangeMetricsData;

    private PoeLeague $league;

    private ExchangeType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->league = $this->league('Seed League');
        $this->type = $this->exchangeType('type-seed');
    }

    public function test_it_seeds_the_min_price_as_the_configured_percentile_of_item_prices(): void
    {
        $this->item($this->league, 'divine');
        $rates = [];

        foreach (range(1, 5) as $number) {
            $this->item($this->league, "item-{$number}");
            $rates["item-{$number}"] = (float) $number;
        }

        $this->snapshot($this->league, $this->type, '2026-11-01 00:00:00+00', $rates, ['divine' => 1.0]);

        $result = $this->seedLeague();

        $this->assertSame(ExchangeMetricsSeedStatusEnum::Seeded, $result->status);
        // default percentile 0.6: position 0.6 * 4 = 2.4 over the prices [1..5]
        $this->assertEqualsWithDelta(3.4, $this->minPrice('divine'), 1e-6);
        $this->assertSame(5, $result->items[0]['sample_size']);
    }

    public function test_it_measures_prices_in_each_currency_by_its_core_rate(): void
    {
        $this->item($this->league, 'divine');
        $this->item($this->league, 'chaos');
        $this->item($this->league, 'item-a');
        $this->item($this->league, 'item-b');

        $this->snapshot(
            $this->league,
            $this->type,
            '2026-11-01 00:00:00+00',
            ['item-a' => 10.0, 'item-b' => 20.0],
            ['divine' => 1.0, 'chaos' => 100.0],
        );

        $this->seedLeague();

        // prices [10, 20] in divine and [1000, 2000] in chaos, position 0.6 over each
        $this->assertEqualsWithDelta(16.0, $this->minPrice('divine'), 1e-6);
        $this->assertEqualsWithDelta(1600.0, $this->minPrice('chaos'), 1e-6);
    }

    public function test_it_leaves_out_the_currency_itself_and_items_with_a_non_positive_rate(): void
    {
        $this->item($this->league, 'divine');
        $this->item($this->league, 'chaos');
        $this->item($this->league, 'item-a');
        $this->item($this->league, 'item-b');

        $this->snapshot(
            $this->league,
            $this->type,
            '2026-11-01 00:00:00+00',
            ['chaos' => 1000.0, 'item-a' => 10.0, 'item-b' => 0.0],
            ['divine' => 1.0, 'chaos' => 100.0],
        );

        $result = $this->seedLeague();

        $sampleSizes = array_column($result->items, 'sample_size', 'name');

        $this->assertSame(2, $sampleSizes['DIVINE']);
        $this->assertSame(1, $sampleSizes['CHAOS']);
        // prices [10, 1000]: the non-positive rate is left out, position 0.6 over the two left
        $this->assertEqualsWithDelta(604.0, $this->minPrice('divine'), 1e-6);
    }

    public function test_it_uses_the_latest_price_check_only(): void
    {
        $this->item($this->league, 'divine');
        $this->item($this->league, 'item-a');

        $this->snapshot($this->league, $this->type, '2026-11-01 00:00:00+00', ['item-a' => 1.0]);
        $this->snapshot($this->league, $this->type, '2026-11-02 00:00:00+00', ['item-a' => 50.0]);

        $this->seedLeague();

        $this->assertEqualsWithDelta(50.0, $this->minPrice('divine'), 1e-6);
    }

    public function test_it_skips_a_currency_that_has_no_exchange_item_in_the_league(): void
    {
        $this->item($this->league, 'divine');
        $this->item($this->league, 'item-a');

        $this->snapshot(
            $this->league,
            $this->type,
            '2026-11-01 00:00:00+00',
            ['item-a' => 10.0],
            ['divine' => 1.0, 'unknown' => 2.0],
        );

        $this->seedLeague();

        $this->assertSame(1, ExchangeMetricsItem::query()->where('poe_league_id', $this->league->id)->count());
    }

    public function test_it_does_nothing_when_the_league_already_has_metrics_items(): void
    {
        $divine = $this->item($this->league, 'divine');
        $this->item($this->league, 'item-a');
        $this->metricsItem($this->league, $divine, 3.0);

        $this->snapshot($this->league, $this->type, '2026-11-01 00:00:00+00', ['item-a' => 10.0]);

        $result = $this->seedLeague();

        $this->assertSame(ExchangeMetricsSeedStatusEnum::AlreadySeeded, $result->status);
        $this->assertEqualsWithDelta(3.0, $this->minPrice('divine'), 1e-6);
    }

    public function test_it_reports_a_league_without_price_checks(): void
    {
        $this->assertSame(ExchangeMetricsSeedStatusEnum::NoPriceCheck, $this->seedLeague()->status);
    }

    public function test_it_reports_a_price_check_without_priced_items(): void
    {
        $this->item($this->league, 'divine');

        $this->snapshot($this->league, $this->type, '2026-11-01 00:00:00+00', []);

        $result = $this->seedLeague();

        $this->assertSame(ExchangeMetricsSeedStatusEnum::NoPricedItems, $result->status);
        $this->assertSame(0, ExchangeMetricsItem::query()->where('poe_league_id', $this->league->id)->count());
    }

    public function test_it_does_not_touch_other_leagues(): void
    {
        $other = $this->league('Other Seed League');
        $this->item($this->league, 'divine');
        $this->item($this->league, 'item-a');
        $this->item($other, 'divine');

        $this->snapshot($this->league, $this->type, '2026-11-01 00:00:00+00', ['item-a' => 10.0]);

        $this->seedLeague();

        $this->assertSame(0, ExchangeMetricsItem::query()->where('poe_league_id', $other->id)->count());
    }

    public function test_mix_adds_the_currency_with_the_configured_percentile_of_prices_in_it(): void
    {
        $this->item($this->league, 'divine');
        $this->item($this->league, 'mirror');

        foreach (['a' => 10.0, 'b' => 20.0, 'c' => 30.0, 'd' => 40.0] as $name => $rate) {
            $this->item($this->league, "item-{$name}");
            $rates["item-{$name}"] = $rate;
        }

        $rates['mirror'] = 100.0;
        $this->snapshot($this->league, $this->type, '2026-11-01 00:00:00+00', $rates);

        $result = $this->mixLeague('mirror');

        // prices in mirror are [0.1, 0.2, 0.3, 0.4], position 0.6 * 3 = 1.8
        $this->assertSame(ExchangeMetricsMixStatusEnum::Added, $result->status);
        $this->assertEqualsWithDelta(0.28, $this->minPrice('mirror'), 1e-6);
        $this->assertSame(4, $result->sample_size);
        $this->assertSame('MIRROR', $result->name);
    }

    public function test_mix_works_when_the_league_already_has_other_metrics_items(): void
    {
        $divine = $this->item($this->league, 'divine');
        $this->item($this->league, 'mirror');
        $this->item($this->league, 'item-a');
        $this->metricsItem($this->league, $divine, 3.0);

        $this->snapshot($this->league, $this->type, '2026-11-01 00:00:00+00', ['mirror' => 100.0, 'item-a' => 10.0]);

        $this->assertSame(ExchangeMetricsMixStatusEnum::Added, $this->mixLeague('mirror')->status);
        $this->assertSame(2, ExchangeMetricsItem::query()->where('poe_league_id', $this->league->id)->count());
        $this->assertEqualsWithDelta(3.0, $this->minPrice('divine'), 1e-6);
    }

    public function test_mix_leaves_out_items_with_a_non_positive_rate(): void
    {
        $this->item($this->league, 'divine');
        $this->item($this->league, 'mirror');
        $this->item($this->league, 'item-a');
        $this->item($this->league, 'item-b');

        $this->snapshot($this->league, $this->type, '2026-11-01 00:00:00+00', [
            'mirror' => 100.0,
            'item-a' => 10.0,
            'item-b' => 0.0,
        ]);

        $result = $this->mixLeague('mirror');

        $this->assertSame(1, $result->sample_size);
        $this->assertEqualsWithDelta(0.1, $this->minPrice('mirror'), 1e-6);
    }

    public function test_mix_uses_the_latest_price_check_only(): void
    {
        $this->item($this->league, 'divine');
        $this->item($this->league, 'mirror');
        $this->item($this->league, 'item-a');

        $this->snapshot($this->league, $this->type, '2026-11-01 00:00:00+00', ['mirror' => 10.0, 'item-a' => 10.0]);
        $this->snapshot($this->league, $this->type, '2026-11-02 00:00:00+00', ['mirror' => 100.0, 'item-a' => 10.0]);

        $this->mixLeague('mirror');

        $this->assertEqualsWithDelta(0.1, $this->minPrice('mirror'), 1e-6);
    }

    public function test_mix_keeps_the_existing_row_untouched(): void
    {
        $mirror = $this->item($this->league, 'mirror');
        $this->item($this->league, 'item-a');
        $this->metricsItem($this->league, $mirror, 5.0);

        $this->snapshot($this->league, $this->type, '2026-11-01 00:00:00+00', ['mirror' => 100.0, 'item-a' => 10.0]);

        $this->assertSame(ExchangeMetricsMixStatusEnum::AlreadyExists, $this->mixLeague('mirror')->status);
        $this->assertEqualsWithDelta(5.0, $this->minPrice('mirror'), 1e-6);
    }

    public function test_mix_reports_an_unknown_trade_id(): void
    {
        $this->item($this->league, 'divine');
        $this->item($this->league, 'mirror');
        $this->snapshot($this->league, $this->type, '2026-11-01 00:00:00+00', ['mirror' => 100.0]);

        $this->assertSame(ExchangeMetricsMixStatusEnum::ItemNotFound, $this->mixLeague('unknown')->status);
    }

    public function test_mix_does_not_find_an_item_of_another_league(): void
    {
        $other = $this->league('Other Mix League');
        $this->item($other, 'mirror');
        $this->snapshot($this->league, $this->type, '2026-11-01 00:00:00+00', []);

        $this->assertSame(ExchangeMetricsMixStatusEnum::ItemNotFound, $this->mixLeague('mirror')->status);
    }

    public function test_mix_reports_a_league_without_price_checks(): void
    {
        $this->item($this->league, 'mirror');

        $this->assertSame(ExchangeMetricsMixStatusEnum::NoPriceCheck, $this->mixLeague('mirror')->status);
    }

    public function test_mix_reports_an_item_without_a_price_in_the_latest_price_check(): void
    {
        $this->item($this->league, 'mirror');
        $this->item($this->league, 'item-a');
        $this->snapshot($this->league, $this->type, '2026-11-01 00:00:00+00', ['item-a' => 10.0]);

        $this->assertSame(ExchangeMetricsMixStatusEnum::NoItemPrice, $this->mixLeague('mirror')->status);
        $this->assertSame(0, ExchangeMetricsItem::query()->where('poe_league_id', $this->league->id)->count());
    }

    public function test_mix_reports_a_price_check_without_other_priced_items(): void
    {
        $this->item($this->league, 'mirror');
        $this->snapshot($this->league, $this->type, '2026-11-01 00:00:00+00', ['mirror' => 100.0]);

        $this->assertSame(ExchangeMetricsMixStatusEnum::NoPricedItems, $this->mixLeague('mirror')->status);
    }

    private function seedLeague(): ExchangeMetricsSeedResultDto
    {
        return $this->app->make(ExchangeMetricsItemSeedServiceContract::class)->seedForLeague($this->league->id);
    }

    private function minPrice(string $tradeId): float
    {
        return (float) ExchangeMetricsItem::query()
            ->where('poe_league_id', $this->league->id)
            ->whereHas('exchangeItem', static fn ($query) => $query->where('trade_id', $tradeId))
            ->sole()
            ->min_price;
    }

    private function mixLeague(string $tradeId): ExchangeMetricsMixResultDto
    {
        return $this->app->make(ExchangeMetricsItemSeedServiceContract::class)->mixItemForLeague($this->league->id, $tradeId);
    }
}
