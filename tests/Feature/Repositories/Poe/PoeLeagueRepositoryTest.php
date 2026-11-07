<?php

namespace Tests\Feature\Repositories\Poe;

use App\DataTransferObjects\RepositoryObjects\League\ExtendedPoeLeagueDto;
use App\DataTransferObjects\RepositoryObjects\League\MainPoeLeagueDto;
use App\DataTransferObjects\RepositoryObjects\League\PoeLeagueDto;
use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\Exchange\Metrics\ExchangeMarketMetric;
use App\Models\Poe\Exchange\Metrics\ExchangeMetricsItem;
use App\Models\Poe\Exchange\PriceCheck;
use App\Models\Poe\LeagueCheckRule;
use App\Models\Poe\PoeLeague;
use App\Models\Poe\PoeVersion;
use App\Repositories\Poe\PoeLeagueRepositoryContract;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Tests\FeatureTestCase;

class PoeLeagueRepositoryTest extends FeatureTestCase
{
    public function test_find_returns_the_dto_of_the_league(): void
    {
        $league = $this->league('Find League', isActive: false);

        $dto = $this->repository()->find($league->id);

        $this->assertInstanceOf(PoeLeagueDto::class, $dto);
        $this->assertSame($league->id, $dto->id);
        $this->assertSame('Find League', $dto->league_name);
        $this->assertFalse($dto->is_active);
        $this->assertSame($league->poe_version_id, $dto->poe_version_id);
    }

    public function test_find_throws_when_the_league_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository()->find(PHP_INT_MAX);
    }

    public function test_get_all_active_returns_only_active_leagues_of_the_version(): void
    {
        $poe2Id = $this->poeVersionId('poe2');

        $active = $this->league('Active League', versionId: $poe2Id, isActive: true);
        $inactive = $this->league('Inactive League', versionId: $poe2Id, isActive: false);
        $otherVersion = $this->league('Other Version League', versionId: $this->poeVersionId('poe1'), isActive: true);

        $result = $this->repository()->getAllActive($poe2Id);

        $this->assertContainsOnlyInstancesOf(PoeLeagueDto::class, $result);
        $this->assertContains($active->id, array_column($result, 'id'));
        $this->assertNotContains($inactive->id, array_column($result, 'id'));
        $this->assertNotContains($otherVersion->id, array_column($result, 'id'));
        $this->assertEmpty(array_filter($result, static fn (PoeLeagueDto $dto): bool => ! $dto->is_active));
    }

    public function test_get_active_ids_with_metrics_items_returns_only_active_leagues_that_have_metrics_items(): void
    {
        $withItems = $this->league('Metrics League');
        $withoutItems = $this->league('No Metrics League');
        $inactiveWithItems = $this->league('Inactive Metrics League', isActive: false);

        foreach ([$withItems, $inactiveWithItems] as $league) {
            $item = ExchangeItem::query()->create([
                'name' => 'DIVINE',
                'trade_id' => 'divine',
                'poe_league_id' => $league->id,
            ]);
            ExchangeMetricsItem::factory()->forLeagueCurrency($league, $item)->create();
        }

        $result = $this->repository()->getActiveIdsWithMetricsItems();

        $this->assertContains($withItems->id, $result);
        $this->assertNotContains($withoutItems->id, $result);
        $this->assertNotContains($inactiveWithItems->id, $result);
    }

    public function test_get_all_active_is_empty_when_no_league_is_active(): void
    {
        PoeLeague::query()->update(['is_active' => false]);

        $this->assertSame([], $this->repository()->getAllActive($this->poeVersionId('poe2')));
    }

    public function test_get_all_active_with_no_rule_returns_leagues_without_a_rule(): void
    {
        $versionId = $this->versionRow();

        $noRule = $this->league('No Rule League', versionId: $versionId);
        $withRule = $this->league('With Rule League', versionId: $versionId);
        $this->rule($withRule, 15);

        $result = $this->repository()->getAllActiveWithNoRule($versionId);

        $this->assertContainsOnlyInstancesOf(PoeLeagueDto::class, $result);
        $this->assertSame([$noRule->id], array_column($result, 'id'));
    }

    public function test_get_all_active_with_no_rule_skips_inactive_and_other_version_leagues(): void
    {
        $versionId = $this->versionRow();

        $this->league('Inactive No Rule League', versionId: $versionId, isActive: false);
        $this->league('Other Version No Rule League', versionId: $this->versionRow());

        $this->assertSame([], $this->repository()->getAllActiveWithNoRule($versionId));
    }

    public function test_get_all_active_with_no_rule_returns_an_empty_array_when_every_league_has_a_rule(): void
    {
        $versionId = $this->versionRow();
        $this->rule($this->league('Ruled League', versionId: $versionId), 15);

        $this->assertSame([], $this->repository()->getAllActiveWithNoRule($versionId));
    }

    public function test_get_with_last_price_check_and_rule_returns_only_active_leagues_of_the_version(): void
    {
        $versionId = $this->versionRow();

        $active = $this->league('Extended Active League', versionId: $versionId, isActive: true);
        $inactive = $this->league('Extended Inactive League', versionId: $versionId, isActive: false);
        $otherVersion = $this->league('Extended Other Version League', versionId: $this->versionRow());

        $result = $this->repository()->getWithLastPriceCheckAndRule($versionId);

        $this->assertContainsOnlyInstancesOf(ExtendedPoeLeagueDto::class, $result);
        $this->assertSame([$active->id], array_column($result, 'id'));
        $this->assertNotContains($inactive->id, array_column($result, 'id'));
        $this->assertNotContains($otherVersion->id, array_column($result, 'id'));
    }

    public function test_get_with_last_price_check_and_rule_maps_the_league_fields(): void
    {
        $versionId = $this->versionRow();
        $league = $this->league('Extended Fields League', ninjaId: 'extended-fields', versionId: $versionId);

        $dto = $this->onlyResult($this->repository()->getWithLastPriceCheckAndRule($versionId));

        $this->assertSame($league->id, $dto->id);
        $this->assertSame('Extended Fields League', $dto->league_name);
        $this->assertSame('extended-fields', $dto->ninja_league_id);
        $this->assertTrue($dto->is_active);
        $this->assertSame($versionId, $dto->poe_version_id);
    }

    public function test_get_with_last_price_check_and_rule_includes_the_check_rule(): void
    {
        $versionId = $this->versionRow();
        $league = $this->league('Extended Rule League', versionId: $versionId);
        $this->rule($league, 15);

        $dto = $this->onlyResult($this->repository()->getWithLastPriceCheckAndRule($versionId));

        $this->assertNotNull($dto->check_rule);
        $this->assertSame(15, $dto->check_rule->minutes_check);
        $this->assertSame($league->id, $dto->check_rule->poe_league_id);
    }

    public function test_get_with_last_price_check_and_rule_leaves_the_rule_null_when_the_league_has_none(): void
    {
        $versionId = $this->versionRow();
        $this->league('Extended No Rule League', versionId: $versionId);

        $dto = $this->onlyResult($this->repository()->getWithLastPriceCheckAndRule($versionId));

        $this->assertNull($dto->check_rule);
    }

    public function test_get_with_last_price_check_and_rule_includes_the_league_version(): void
    {
        $versionId = $this->versionRow();
        $this->league('Extended Version League', versionId: $versionId);

        $dto = $this->onlyResult($this->repository()->getWithLastPriceCheckAndRule($versionId));

        $this->assertSame($versionId, $dto->poe_version->id);
        $this->assertSame('poe2', $dto->poe_version->version);
    }

    public function test_get_with_last_price_check_and_rule_includes_only_the_latest_price_check(): void
    {
        $versionId = $this->versionRow();
        $league = $this->league('Extended Latest League', versionId: $versionId);

        $older = $this->priceCheck($league, '2026-01-01 00:00:00');
        $newer = $this->priceCheck($league, '2026-02-01 00:00:00');

        $dto = $this->onlyResult($this->repository()->getWithLastPriceCheckAndRule($versionId));

        $this->assertCount(1, $dto->latest_price_check);
        $this->assertSame($newer->id, $dto->latest_price_check[0]->id);
        $this->assertNotSame($older->id, $dto->latest_price_check[0]->id);
        $this->assertSame($league->id, $dto->latest_price_check[0]->poe_league_id);
    }

    public function test_get_with_last_price_check_and_rule_keeps_the_latest_price_check_per_league(): void
    {
        $versionId = $this->versionRow();
        $first = $this->league('Extended First League', versionId: $versionId);
        $second = $this->league('Extended Second League', versionId: $versionId);

        $firstCheck = $this->priceCheck($first, '2026-03-01 00:00:00');
        $secondCheck = $this->priceCheck($second, '2026-04-01 00:00:00');

        $result = $this->repository()->getWithLastPriceCheckAndRule($versionId);

        $this->assertSame($firstCheck->id, $this->findById($result, $first->id)->latest_price_check[0]->id);
        $this->assertSame($secondCheck->id, $this->findById($result, $second->id)->latest_price_check[0]->id);
    }

    public function test_get_with_last_price_check_and_rule_returns_an_empty_list_when_the_league_has_no_price_check(): void
    {
        $versionId = $this->versionRow();
        $this->league('Extended No Check League', versionId: $versionId);

        $dto = $this->onlyResult($this->repository()->getWithLastPriceCheckAndRule($versionId));

        $this->assertSame([], $dto->latest_price_check);
    }

    public function test_get_with_last_price_check_and_rule_returns_an_empty_array_when_no_league_is_active(): void
    {
        $versionId = $this->versionRow();

        $this->assertSame([], $this->repository()->getWithLastPriceCheckAndRule($versionId));
    }

    public function test_upsert_inserts_new_leagues(): void
    {
        $poe1Id = $this->poeVersionId('poe1');
        $poe2Id = $this->poeVersionId('poe2');

        $this->repository()->upsert([
            ['league_name' => 'Upsert League One', 'ninja_league_id' => 'upsert-one', 'slug' => 'v-upsert-one', 'started_at' => null, 'poe_version_id' => $poe1Id, 'is_active' => true],
            ['league_name' => 'Upsert League Two', 'ninja_league_id' => 'upsert-two', 'slug' => 'v-upsert-two', 'started_at' => null, 'poe_version_id' => $poe2Id, 'is_active' => false],
        ]);

        $this->assertDatabaseHas('poe_leagues', [
            'league_name' => 'Upsert League One',
            'ninja_league_id' => 'upsert-one', 'slug' => 'v-upsert-one', 'started_at' => null,
            'poe_version_id' => $poe1Id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('poe_leagues', [
            'league_name' => 'Upsert League Two',
            'ninja_league_id' => 'upsert-two', 'slug' => 'v-upsert-two', 'started_at' => null,
            'poe_version_id' => $poe2Id,
            'is_active' => false,
        ]);
    }

    public function test_upsert_updates_an_existing_league_matched_by_ninja_id(): void
    {
        $league = $this->league('Upsert Existing League', ninjaId: 'upsert-existing', isActive: true);

        $this->repository()->upsert([
            [
                'league_name' => 'Upsert Existing League Renamed',
                'ninja_league_id' => 'upsert-existing', 'slug' => 'v-upsert-existing', 'started_at' => null,
                'poe_version_id' => $league->poe_version_id,
                'is_active' => false,
            ],
        ]);

        $this->assertSame(
            1,
            PoeLeague::query()
                ->where('ninja_league_id', 'upsert-existing')
                ->where('poe_version_id', $league->poe_version_id)
                ->count(),
        );
        $this->assertDatabaseHas('poe_leagues', [
            'id' => $league->id,
            'league_name' => 'Upsert Existing League Renamed',
            'is_active' => false,
        ]);
    }

    public function test_upsert_keeps_slug_and_started_at_of_an_existing_league(): void
    {
        $league = $this->league('Upsert Keep Slug', ninjaId: 'upsert-keep');
        $league->update(['started_at' => '2030-01-01 00:00:00+00']);
        $slug = $league->slug;

        $this->repository()->upsert([
            [
                'league_name' => 'Upsert Keep Slug',
                'ninja_league_id' => 'upsert-keep',
                'slug' => 'must-not-overwrite',
                'started_at' => '2031-01-01 00:00:00+00',
                'poe_version_id' => $league->poe_version_id,
                'is_active' => false,
            ],
        ]);

        $this->assertSame($slug, $league->fresh()->slug);
        $this->assertStringStartsWith('2030-01-01', (string) $league->fresh()->started_at);
    }

    public function test_upsert_keeps_the_same_ninja_id_separate_for_each_version(): void
    {
        $this->repository()->upsert([
            ['league_name' => 'Same Name League', 'ninja_league_id' => 'same-ninja', 'slug' => 'poe1-same-ninja', 'started_at' => null, 'poe_version_id' => $this->poeVersionId('poe1'), 'is_active' => true],
            ['league_name' => 'Same Name League', 'ninja_league_id' => 'same-ninja', 'slug' => 'poe2-same-ninja', 'started_at' => null, 'poe_version_id' => $this->poeVersionId('poe2'), 'is_active' => true],
        ]);

        $this->assertSame(2, PoeLeague::query()->where('ninja_league_id', 'same-ninja')->count());
    }

    public function test_upsert_default_rules_uses_the_given_interval(): void
    {
        $league = $this->league('Default Rule League');

        $this->repository()->upsertDefaultRulesForLeagues([$league->id], 300);

        $this->assertDatabaseHas('league_check_rules', [
            'poe_league_id' => $league->id,
            'minutes_check' => 300,
        ]);
    }

    public function test_upsert_default_rules_updates_an_existing_rule(): void
    {
        $league = $this->league('Existing Rule League');
        $this->rule($league, 60);

        $this->repository()->upsertDefaultRulesForLeagues([$league->id], 120);

        $this->assertSame(1, LeagueCheckRule::query()->where('poe_league_id', $league->id)->count());
        $this->assertDatabaseHas('league_check_rules', [
            'poe_league_id' => $league->id,
            'minutes_check' => 120,
        ]);
    }

    public function test_upsert_default_rules_writes_the_same_interval_for_every_league(): void
    {
        $first = $this->league('First Default Rule League');
        $second = $this->league('Second Default Rule League');

        $this->repository()->upsertDefaultRulesForLeagues([$first->id, $second->id], 45);

        $this->assertDatabaseHas('league_check_rules', [
            'poe_league_id' => $first->id,
            'minutes_check' => 45,
        ]);
        $this->assertDatabaseHas('league_check_rules', [
            'poe_league_id' => $second->id,
            'minutes_check' => 45,
        ]);
    }

    public function test_upsert_default_rules_accepts_an_empty_list(): void
    {
        $before = LeagueCheckRule::query()->count();

        $this->repository()->upsertDefaultRulesForLeagues([], 300);

        $this->assertSame($before, LeagueCheckRule::query()->count());
    }

    public function test_all_main_leagues_returns_only_active_leagues_with_market_metrics_of_the_currency(): void
    {
        $withMetrics = $this->leagueWithMarketMetrics('Main With Metrics', 'divine', [['2030-01-01 00:00:00', 0.01, 3]]);
        $inactive = $this->leagueWithMarketMetrics('Main Inactive', 'divine', [['2030-01-01 00:00:00', 0.01, 3]], isActive: false);
        $withoutMetrics = $this->league('Main Without Metrics');

        $ids = array_column($this->repository()->allMainLeaguesWithSparkline('divine', $this->since()), 'id');

        $this->assertContains($withMetrics->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
        $this->assertNotContains($withoutMetrics->id, $ids);
    }

    public function test_all_main_leagues_maps_the_league_fields_and_version(): void
    {
        $league = $this->leagueWithMarketMetrics('Main Mapped', 'divine', [['2030-01-01 00:00:00', 0.0132, 40]]);

        $dto = $this->mainLeagueById($league->id);

        $this->assertSame('Main Mapped', $dto->league_name);
        $this->assertSame($league->ninja_league_id, $dto->ninja_league_id);
        $this->assertSame($league->slug, $dto->slug);
        $this->assertTrue($dto->is_active);
        $this->assertSame($league->poe_version_id, $dto->poe_version_id);
        $this->assertSame('poe2', $dto->poe_version->version);
    }

    public function test_all_main_leagues_orders_market_metrics_by_bucket_ascending(): void
    {
        $league = $this->leagueWithMarketMetrics('Main Ordered', 'divine', [
            ['2030-01-03 00:00:00', 0.03, 3],
            ['2030-01-01 00:00:00', 0.01, 1],
            ['2030-01-02 00:00:00', 0.02, 2],
        ]);

        $metrics = $this->mainLeagueById($league->id)->exchange_market_metrics;

        $this->assertSame([1, 2, 3], array_column($metrics, 'basket_size'));
        $this->assertSame([0.01, 0.02, 0.03], array_column($metrics, 'market_return'));
    }

    public function test_all_main_leagues_keeps_only_market_metrics_of_the_requested_currency(): void
    {
        $league = $this->leagueWithMarketMetrics('Main Currency', 'divine', [['2030-01-01 00:00:00', 0.01, 1]]);
        $chaos = $this->metricsItemFor($league, 'chaos');
        ExchangeMarketMetric::factory()->forMetricsItem($chaos)->atBucket('2030-01-02 00:00:00')->state(['basket_size' => 9])->create();

        $divine = $this->mainLeagueById($league->id)->exchange_market_metrics;

        $this->assertCount(1, $divine);
        $this->assertSame(1, $divine[0]->basket_size);

        $chaosLeague = $this->mainLeagueById($league->id, 'chaos');

        $this->assertCount(1, $chaosLeague->exchange_market_metrics);
        $this->assertSame(9, $chaosLeague->exchange_market_metrics[0]->basket_size);
    }

    public function test_all_main_leagues_skips_a_league_with_metrics_only_of_another_currency(): void
    {
        $league = $this->leagueWithMarketMetrics('Main Other Currency', 'chaos', [['2030-01-01 00:00:00', 0.01, 1]]);

        $this->assertNotContains($league->id, array_column($this->repository()->allMainLeaguesWithSparkline('divine', $this->since()), 'id'));
    }

    public function test_all_main_leagues_keeps_only_buckets_inside_the_window(): void
    {
        $since = now()->subDays(7);
        $league = $this->leagueWithMarketMetrics('Main Window', 'divine', [
            [now()->subDays(8)->toDateTimeString(), 0.01, 1],
            [now()->subDays(6)->toDateTimeString(), 0.02, 2],
            [now()->subDay()->toDateTimeString(), 0.03, 3],
        ]);

        $metrics = $this->mainLeagueById($league->id, since: $since)->exchange_market_metrics;

        $this->assertSame([2, 3], array_column($metrics, 'basket_size'));
    }

    public function test_all_main_leagues_skips_a_league_whose_buckets_are_all_outside_the_window(): void
    {
        $league = $this->leagueWithMarketMetrics('Main Stale', 'divine', [[now()->subDays(30)->toDateTimeString(), 0.01, 1]]);

        $this->assertNotContains($league->id, array_column($this->repository()->allMainLeaguesWithSparkline('divine', $this->since()), 'id'));
    }

    public function test_all_main_leagues_is_ordered_by_version_then_id(): void
    {
        $poe2 = $this->leagueWithMarketMetrics('Main Order Poe2', 'divine', [['2030-01-01 00:00:00', 0.01, 1]]);
        $poe1 = $this->leagueWithMarketMetrics('Main Order Poe1', 'divine', [['2030-01-01 00:00:00', 0.01, 1]], versionId: $this->poeVersionId('poe1'));
        $poe2Later = $this->leagueWithMarketMetrics('Main Order Poe2 Later', 'divine', [['2030-01-01 00:00:00', 0.01, 1]]);

        $ids = array_values(array_intersect(
            array_column($this->repository()->allMainLeaguesWithSparkline('divine', $this->since()), 'id'),
            [$poe2->id, $poe1->id, $poe2Later->id],
        ));

        $this->assertSame([$poe1->id, $poe2->id, $poe2Later->id], $ids);
    }

    public function test_all_main_leagues_includes_only_the_latest_price_check(): void
    {
        $league = $this->leagueWithMarketMetrics('Main Latest Check', 'divine', [['2030-01-01 00:00:00', 0.01, 1]]);
        $this->priceCheck($league, '2030-01-01 10:00:00');
        $latest = $this->priceCheck($league, '2030-01-01 12:00:00');

        $checks = $this->mainLeagueById($league->id)->latest_price_check;

        $this->assertCount(1, $checks);
        $this->assertSame($latest->id, $checks[0]->id);
    }

    public function test_all_main_leagues_has_no_price_check_when_the_league_has_none(): void
    {
        $league = $this->leagueWithMarketMetrics('Main No Check', 'divine', [['2030-01-01 00:00:00', 0.01, 1]]);

        $this->assertEmpty($this->mainLeagueById($league->id)->latest_price_check);
    }

    private function since(): CarbonInterface
    {
        return now()->subDays(7);
    }

    private function repository(): PoeLeagueRepositoryContract
    {
        return $this->app->make(PoeLeagueRepositoryContract::class);
    }

    private function poeVersionId(string $version): int
    {
        return (int) PoeVersion::query()->where('version', $version)->value('id');
    }

    /**
     * Each test that asserts the full result set owns its own version row, so the shared
     * test database cannot add leagues or exchange data the test did not create. The
     * version string stays a real one where the value itself is asserted.
     */
    private function versionRow(string $version = 'poe2'): int
    {
        $id = (int) DB::table('poe_versions')->max('id') + 1;

        DB::table('poe_versions')->insert(['id' => $id, 'version' => $version]);

        return $id;
    }

    private function league(
        string $name,
        ?string $ninjaId = null,
        ?int $versionId = null,
        bool $isActive = true,
    ): PoeLeague {
        $versionId ??= $this->poeVersionId('poe2');
        $ninjaId ??= 'ninja-'.strtolower(str_replace(' ', '-', $name));

        return PoeLeague::query()->create([
            'league_name' => $name,
            'ninja_league_id' => $ninjaId,
            'slug' => 'v'.$versionId.'-'.$ninjaId,
            'is_active' => $isActive,
            'poe_version_id' => $versionId,
        ]);
    }

    private function rule(PoeLeague $league, ?int $minutesCheck): LeagueCheckRule
    {
        return LeagueCheckRule::query()->create([
            'poe_league_id' => $league->id,
            'minutes_check' => $minutesCheck,
        ]);
    }

    private function priceCheck(PoeLeague $league, string $createdAt): PriceCheck
    {
        return PriceCheck::query()->create([
            'poe_league_id' => $league->id,
            'created_at' => $createdAt,
        ]);
    }

    /**
     * @param  ExtendedPoeLeagueDto[]  $dtos
     */
    private function onlyResult(array $dtos): ExtendedPoeLeagueDto
    {
        $this->assertCount(1, $dtos);

        return $dtos[0];
    }

    /**
     * @param  ExtendedPoeLeagueDto[]  $dtos
     */
    private function findById(array $dtos, int $id): ExtendedPoeLeagueDto
    {
        foreach ($dtos as $dto) {
            if ($dto->id === $id) {
                return $dto;
            }
        }

        $this->fail("No league dto with id [{$id}] was returned.");
    }

    /**
     * @param  array<int, array{0: string, 1: float, 2: int}>  $buckets  bucket start, market return, basket size
     */
    private function leagueWithMarketMetrics(string $name, string $tradeId, array $buckets, bool $isActive = true, ?int $versionId = null): PoeLeague
    {
        $league = $this->league($name, versionId: $versionId, isActive: $isActive);
        $metricsItem = $this->metricsItemFor($league, $tradeId);

        foreach ($buckets as [$bucketStart, $marketReturn, $basketSize]) {
            ExchangeMarketMetric::factory()
                ->forMetricsItem($metricsItem)
                ->atBucket($bucketStart)
                ->withReturn($marketReturn)
                ->state(['basket_size' => $basketSize])
                ->create();
        }

        return $league;
    }

    private function metricsItemFor(PoeLeague $league, string $tradeId): ExchangeMetricsItem
    {
        $item = ExchangeItem::query()->create([
            'name' => strtoupper($tradeId),
            'trade_id' => $tradeId,
            'poe_league_id' => $league->id,
        ]);

        return ExchangeMetricsItem::factory()->forLeagueCurrency($league, $item)->create();
    }

    private function mainLeagueById(int $id, string $tradeId = 'divine', ?CarbonInterface $since = null): MainPoeLeagueDto
    {
        foreach ($this->repository()->allMainLeaguesWithSparkline($tradeId, $since ?? $this->since()) as $dto) {
            if ($dto->id === $id) {
                return $dto;
            }
        }

        $this->fail("No main league dto with id [{$id}] was returned.");
    }
}
