<?php

namespace Tests\Feature\Repositories\Poe;

use App\DataTransferObjects\RepositoryObjects\League\PoeLeagueDto;
use App\DataTransferObjects\RepositoryObjects\League\PoeLeagueWithLastCoreDto;
use App\Models\Poe\Exchange\PriceCheckCore;
use App\Models\Poe\LeagueCheckRule;
use App\Models\Poe\PoeLeague;
use App\Models\Poe\PoeVersion;
use App\Repositories\Poe\PoeLeagueRepositoryContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
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

    public function test_find_includes_the_check_rule(): void
    {
        $league = $this->league('Find With Rule League');
        $this->rule($league, 15);

        $dto = $this->repository()->find($league->id);

        $this->assertNotNull($dto->check_rule);
        $this->assertSame(15, $dto->check_rule->minutes_check);
        $this->assertSame($league->id, $dto->check_rule->poe_league_id);
    }

    public function test_find_throws_when_the_league_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository()->find(PHP_INT_MAX);
    }

    public function test_find_active_by_ninja_id_and_version_matches_all_three_conditions(): void
    {
        $versionId = $this->poeVersionId('poe2');

        $poe1 = $this->league('Matched League', ninjaId: 'matched-poe1', versionId: $this->poeVersionId('poe1'), isActive: true);
        $active = $this->league('Matched League', ninjaId: 'matched-poe2', versionId: $versionId, isActive: true);

        $dto = $this->repository()->findActiveByNinjaIdVersion('matched-poe2', $versionId);

        $this->assertSame($active->id, $dto->id);
        $this->assertNotSame($poe1->id, $dto->id);
        $this->assertSame('matched-poe2', $dto->ninja_league_id);
        $this->assertTrue($dto->is_active);
    }

    public function test_find_active_by_ninja_id_and_version_throws_when_the_league_is_inactive(): void
    {
        $versionId = $this->poeVersionId('poe2');
        $this->league('Inactive Only League', ninjaId: 'inactive-only', versionId: $versionId, isActive: false);

        $this->expectException(ModelNotFoundException::class);

        $this->repository()->findActiveByNinjaIdVersion('inactive-only', $versionId);
    }

    public function test_find_active_by_ninja_id_and_version_throws_when_the_id_is_unknown(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository()->findActiveByNinjaIdVersion('cant-find-me', $this->poeVersionId('poe2'));
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

    public function test_active_with_latest_core_returns_the_latest_core_of_each_league(): void
    {
        $league = $this->league('Latest Core League', isActive: true);

        $this->core($league, '2030-01-01 10:00:00+00');
        $latest = $this->core($league, '2030-01-01 12:00:00+00');
        $this->core($league, '2030-01-01 11:00:00+00');

        $result = $this->repository()->getActiveWithLatestCore();

        $this->assertContainsOnlyInstancesOf(PoeLeagueWithLastCoreDto::class, $result);

        $dto = $this->findById($result, $league->id);

        $this->assertSame($latest->id, $dto->last_core_id);
        $this->assertSame('Latest Core League', $dto->league_name);
        $this->assertTrue($dto->is_active);
        $this->assertSame(
            Carbon::parse('2030-01-01 12:00:00')->getTimestamp(),
            Carbon::parse($dto->last_core_created_at)->getTimestamp(),
        );
    }

    public function test_active_with_latest_core_resolves_each_league_separately(): void
    {
        $first = $this->league('First Cored League', isActive: true);
        $second = $this->league('Second Cored League', isActive: true);

        $this->core($first, '2030-02-01 10:00:00+00');
        $firstLatest = $this->core($first, '2030-02-02 10:00:00+00');
        $secondLatest = $this->core($second, '2030-02-01 08:00:00+00');

        $result = $this->repository()->getActiveWithLatestCore();

        $this->assertSame($firstLatest->id, $this->findById($result, $first->id)->last_core_id);
        $this->assertSame($secondLatest->id, $this->findById($result, $second->id)->last_core_id);
    }

    public function test_active_with_latest_core_leaves_the_core_empty_when_the_league_has_none(): void
    {
        $league = $this->league('No Core League', isActive: true);

        $dto = $this->findById($this->repository()->getActiveWithLatestCore(), $league->id);

        $this->assertNull($dto->last_core_id);
        $this->assertNull($dto->last_core_created_at);
    }

    public function test_active_with_latest_core_excludes_inactive_leagues(): void
    {
        $inactive = $this->league('Inactive Cored League', isActive: false);
        $this->core($inactive, '2030-03-01 10:00:00+00');

        $result = $this->repository()->getActiveWithLatestCore();

        $this->assertNotContains($inactive->id, array_column($result, 'id'));
    }

    public function test_active_with_latest_core_includes_the_check_rule(): void
    {
        $league = $this->league('Rule Cored League', isActive: true);
        $this->rule($league, 30);

        $dto = $this->findById($this->repository()->getActiveWithLatestCore(), $league->id);

        $this->assertNotNull($dto->check_rule);
        $this->assertSame(30, $dto->check_rule->minutes_check);
    }

    public function test_active_with_latest_core_is_empty_without_active_leagues(): void
    {
        PoeLeague::query()->update(['is_active' => false]);

        $this->assertSame([], $this->repository()->getActiveWithLatestCore());
    }

    private function repository(): PoeLeagueRepositoryContract
    {
        return $this->app->make(PoeLeagueRepositoryContract::class);
    }

    private function poeVersionId(string $version): int
    {
        return (int) PoeVersion::query()->where('version', $version)->value('id');
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

    private function core(PoeLeague $league, string $createdAt): PriceCheckCore
    {
        return PriceCheckCore::query()->create([
            'poe_league_id' => $league->id,
            'created_at' => $createdAt,
        ]);
    }

    /**
     * @param  PoeLeagueWithLastCoreDto[]  $dtos
     */
    private function findById(array $dtos, int $id): PoeLeagueWithLastCoreDto
    {
        foreach ($dtos as $dto) {
            if ($dto->id === $id) {
                return $dto;
            }
        }

        $this->fail("No league dto with id [{$id}] was returned.");
    }
}
