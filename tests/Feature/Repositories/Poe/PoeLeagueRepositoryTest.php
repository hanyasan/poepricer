<?php

namespace Tests\Feature\Repositories\Poe;

use App\DataTransferObjects\RepositoryObjects\League\ExtendedPoeLeagueDto;
use App\DataTransferObjects\RepositoryObjects\League\PoeLeagueDto;
use App\Models\Poe\Exchange\PriceCheck;
use App\Models\Poe\LeagueCheckRule;
use App\Models\Poe\PoeLeague;
use App\Models\Poe\PoeVersion;
use App\Repositories\Poe\PoeLeagueRepositoryContract;
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
            ['league_name' => 'Upsert League One', 'ninja_league_id' => 'upsert-one', 'poe_version_id' => $poe1Id, 'is_active' => true],
            ['league_name' => 'Upsert League Two', 'ninja_league_id' => 'upsert-two', 'poe_version_id' => $poe2Id, 'is_active' => false],
        ]);

        $this->assertDatabaseHas('poe_leagues', [
            'league_name' => 'Upsert League One',
            'ninja_league_id' => 'upsert-one',
            'poe_version_id' => $poe1Id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('poe_leagues', [
            'league_name' => 'Upsert League Two',
            'ninja_league_id' => 'upsert-two',
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
                'ninja_league_id' => 'upsert-existing',
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

    public function test_upsert_keeps_the_same_ninja_id_separate_for_each_version(): void
    {
        $this->repository()->upsert([
            ['league_name' => 'Same Name League', 'ninja_league_id' => 'same-ninja', 'poe_version_id' => $this->poeVersionId('poe1'), 'is_active' => true],
            ['league_name' => 'Same Name League', 'ninja_league_id' => 'same-ninja', 'poe_version_id' => $this->poeVersionId('poe2'), 'is_active' => true],
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
        return PoeLeague::query()->create([
            'league_name' => $name,
            'ninja_league_id' => $ninjaId ?? 'ninja-'.strtolower(str_replace(' ', '-', $name)),
            'is_active' => $isActive,
            'poe_version_id' => $versionId ?? $this->poeVersionId('poe2'),
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
}
