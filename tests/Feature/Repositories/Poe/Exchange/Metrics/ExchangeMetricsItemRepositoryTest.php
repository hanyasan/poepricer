<?php

namespace Tests\Feature\Repositories\Poe\Exchange\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMetricsItemDto;
use App\Repositories\Poe\Exchange\Metrics\ExchangeMetricsItemRepositoryContract;
use Tests\Concerns\BuildsExchangeMetricsData;
use Tests\FeatureTestCase;

class ExchangeMetricsItemRepositoryTest extends FeatureTestCase
{
    use BuildsExchangeMetricsData;

    public function test_it_returns_the_metrics_items_of_the_league_with_their_currency(): void
    {
        $league = $this->league('Metrics Item League');
        $divine = $this->item($league, 'divine');
        $chaos = $this->item($league, 'chaos');

        $divineMetrics = $this->metricsItem($league, $divine, 5.0);
        $this->metricsItem($league, $chaos, 0.25);

        $dtos = $this->repository()->getAllByLeague($league->id);

        $this->assertCount(2, $dtos);
        $this->assertContainsOnlyInstancesOf(ExchangeMetricsItemDto::class, $dtos);
        $this->assertSame($divineMetrics->id, $dtos[0]->id);
        $this->assertSame(5.0, $dtos[0]->min_price);
        $this->assertSame($league->id, $dtos[0]->poe_league_id);
        $this->assertSame($divine->id, $dtos[0]->exchange_item_id);
        $this->assertSame('divine', $dtos[0]->exchange_item->trade_id);
        $this->assertSame('chaos', $dtos[1]->exchange_item->trade_id);
    }

    public function test_it_ignores_the_metrics_items_of_other_leagues(): void
    {
        $league = $this->league('Metrics Own League');
        $other = $this->league('Metrics Other League');

        $this->metricsItem($other, $this->item($other, 'divine'));

        $this->assertSame([], $this->repository()->getAllByLeague($league->id));
    }

    private function repository(): ExchangeMetricsItemRepositoryContract
    {
        return $this->app->make(ExchangeMetricsItemRepositoryContract::class);
    }
}
