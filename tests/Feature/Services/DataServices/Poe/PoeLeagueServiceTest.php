<?php

namespace Tests\Feature\Services\DataServices\Poe;

use App\DataTransferObjects\RepositoryObjects\League\MainPoeLeagueDto;
use App\Enums\Tags\PoeLeagueTagEnum;
use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\Exchange\Metrics\ExchangeMarketMetric;
use App\Models\Poe\Exchange\Metrics\ExchangeMetricsItem;
use App\Models\Poe\PoeLeague;
use App\Models\Poe\PoeVersion;
use App\Repositories\Poe\PoeLeagueRepositoryContract;
use App\Services\DataServices\Poe\PoeLeagueServiceContract;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use Tests\FeatureTestCase;

class PoeLeagueServiceTest extends FeatureTestCase
{
    public function test_main_page_leagues_returns_main_league_dtos(): void
    {
        $league = $this->leagueWithMarketMetrics('Service Main League', 'divine');

        $result = $this->service()->mainPageLeagues();

        $this->assertContainsOnlyInstancesOf(MainPoeLeagueDto::class, $result);
        $this->assertContains($league->id, array_column($result, 'id'));
    }

    public function test_main_page_leagues_uses_the_default_main_trade_id_from_config(): void
    {
        config(['poe.base.default_main_trade_id' => 'chaos']);
        $chaosLeague = $this->leagueWithMarketMetrics('Service Chaos League', 'chaos');
        $divineLeague = $this->leagueWithMarketMetrics('Service Divine League', 'divine');

        $byId = [];
        foreach ($this->service()->mainPageLeagues() as $dto) {
            $byId[$dto->id] = $dto;
        }

        $this->assertCount(1, $byId[$chaosLeague->id]->exchange_market_metrics);
        $this->assertArrayNotHasKey($divineLeague->id, $byId);
    }

    public function test_main_page_leagues_asks_the_repository_once_and_caches_the_result(): void
    {
        config(['poe.base.default_main_trade_id' => 'chaos']);
        $this->travelTo(now());

        $this->mock(PoeLeagueRepositoryContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('allMainLeaguesWithSparkline')
                ->once()
                ->withArgs(static fn (string $tradeId, CarbonInterface $since): bool => $tradeId === 'chaos'
                    && $since->equalTo(now()->subDays(7)))
                ->andReturn([]);
        });

        $service = $this->service();

        $this->assertSame([], $service->mainPageLeagues());
        $this->assertSame([], $service->mainPageLeagues());
    }

    public function test_second_call_does_not_see_a_league_created_after_the_first_call(): void
    {
        $first = $this->leagueWithMarketMetrics('Service First League', 'divine');

        $this->service()->mainPageLeagues();

        $second = $this->leagueWithMarketMetrics('Service Second League', 'divine');

        $ids = array_column($this->service()->mainPageLeagues(), 'id');

        $this->assertContains($first->id, $ids);
        $this->assertNotContains($second->id, $ids);
    }

    public function test_flushing_the_league_tag_drops_the_cached_leagues(): void
    {
        $this->leagueWithMarketMetrics('Service Before Flush League', 'divine');
        $this->service()->mainPageLeagues();

        $later = $this->leagueWithMarketMetrics('Service After Flush League', 'divine');

        Cache::tags([PoeLeagueTagEnum::league->name])->flush();

        $this->assertContains($later->id, array_column($this->service()->mainPageLeagues(), 'id'));
    }

    private function service(): PoeLeagueServiceContract
    {
        return $this->app->make(PoeLeagueServiceContract::class);
    }

    private function leagueWithMarketMetrics(string $name, string $tradeId): PoeLeague
    {
        $league = PoeLeague::query()->create([
            'league_name' => $name,
            'ninja_league_id' => 'ninja-'.strtolower(str_replace(' ', '-', $name)),
            'slug' => 'poe2-ninja-'.strtolower(str_replace(' ', '-', $name)),
            'is_active' => true,
            'poe_version_id' => (int) PoeVersion::query()->where('version', 'poe2')->value('id'),
        ]);

        $item = ExchangeItem::query()->create([
            'name' => strtoupper($tradeId),
            'trade_id' => $tradeId,
            'poe_league_id' => $league->id,
        ]);
        $metricsItem = ExchangeMetricsItem::factory()->forLeagueCurrency($league, $item)->create();

        ExchangeMarketMetric::factory()->forMetricsItem($metricsItem)->create();

        return $league;
    }
}
