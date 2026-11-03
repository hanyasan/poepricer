<?php

namespace Tests\Feature\Services\NinjaImport;

use App\Enums\Tags\PoeLeagueTagEnum;
use App\Models\Poe\LeagueCheckRule;
use App\Models\Poe\PoeLeague;
use App\Models\Poe\PoeVersion;
use App\Services\NinjaImport\NinjaImportServiceContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\FeatureTestCase;

class NinjaImportServiceTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_it_activates_leagues_returned_by_ninja_for_every_version(): void
    {
        $poe1Id = $this->versionId('poe1');
        $poe2Id = $this->versionId('poe2');

        Http::fake([
            'poe.ninja/poe1/*' => Http::response([['name' => 'Settlers1', 'id' => 'Settlers1']]),
            'poe.ninja/poe2/*' => Http::response([['name' => 'Standard1', 'id' => 'Standard1']]),
        ]);

        $this->service()->updateAllLeaguesFromNinja();

        $this->assertDatabaseHas('poe_leagues', [
            'league_name' => 'Settlers1',
            'ninja_league_id' => 'Settlers1',
            'poe_version_id' => $poe1Id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('poe_leagues', [
            'league_name' => 'Standard1',
            'ninja_league_id' => 'Standard1',
            'poe_version_id' => $poe2Id,
            'is_active' => true,
        ]);
    }

    public function test_it_requests_the_leagues_endpoint_for_each_version(): void
    {
        Http::fake(['poe.ninja/*' => Http::response([])]);

        $this->service()->updateAllLeaguesFromNinja();

        $urls = [];

        Http::assertSent(function (Request $request) use (&$urls): bool {
            $urls[] = $request->url();

            return true;
        });

        $this->assertContains('https://poe.ninja/poe1/api/economy/leagues', $urls);
        $this->assertContains('https://poe.ninja/poe2/api/economy/leagues', $urls);
    }

    public function test_it_deactivates_active_leagues_missing_from_ninja(): void
    {
        $poe1Id = $this->versionId('poe1');
        $league = $this->league('Old League', $poe1Id, true);

        Http::fake([
            'poe.ninja/poe1/*' => Http::response([['name' => 'Settlers1', 'id' => 'Settlers1']]),
            'poe.ninja/poe2/*' => Http::response([]),
        ]);

        $this->service()->updateAllLeaguesFromNinja();

        $this->assertDatabaseHas('poe_leagues', ['id' => $league->id, 'is_active' => false]);
        $this->assertDatabaseHas('poe_leagues', [
            'league_name' => 'Settlers1',
            'poe_version_id' => $poe1Id,
            'is_active' => true,
        ]);
    }

    public function test_it_keeps_a_listed_league_active_without_duplicating_it(): void
    {
        $poe1Id = $this->versionId('poe1');
        $league = $this->league('Settlers1', $poe1Id, true);

        Http::fake([
            'poe.ninja/poe1/*' => Http::response([['name' => 'Settlers1', 'id' => 'Settlers1']]),
            'poe.ninja/poe2/*' => Http::response([]),
        ]);

        $this->service()->updateAllLeaguesFromNinja();

        $this->assertSame(
            1,
            PoeLeague::query()->where('league_name', 'Settlers1')->where('poe_version_id', $poe1Id)->count(),
        );
        $this->assertDatabaseHas('poe_leagues', ['id' => $league->id, 'is_active' => true]);
    }

    public function test_it_reactivates_a_league_that_ninja_lists_again(): void
    {
        $poe1Id = $this->versionId('poe1');
        $league = $this->league('Settlers1', $poe1Id, false);

        Http::fake([
            'poe.ninja/poe1/*' => Http::response([['name' => 'Settlers1', 'id' => 'Settlers1']]),
            'poe.ninja/poe2/*' => Http::response([]),
        ]);

        $this->service()->updateAllLeaguesFromNinja();

        $this->assertDatabaseHas('poe_leagues', ['id' => $league->id, 'is_active' => true]);
    }

    public function test_it_does_not_deactivate_leagues_of_another_version(): void
    {
        $poe1Id = $this->versionId('poe1');
        $poe2Id = $this->versionId('poe2');

        $poe2League = $this->league('Standard1', $poe2Id, true);

        Http::fake([
            'poe.ninja/poe1/*' => Http::response([['name' => 'Settlers1', 'id' => 'Settlers1']]),
            'poe.ninja/poe2/*' => Http::response([['name' => 'Standard1', 'id' => 'Standard1']]),
        ]);

        $this->service()->updateAllLeaguesFromNinja();

        $this->assertDatabaseHas('poe_leagues', ['id' => $poe2League->id, 'is_active' => true]);
        $this->assertSame(
            1,
            PoeLeague::query()->where('poe_version_id', $poe1Id)->where('is_active', true)->count(),
        );
    }

    public function test_it_deactivates_a_league_only_within_its_own_version(): void
    {
        $poe1Id = $this->versionId('poe1');
        $poe2Id = $this->versionId('poe2');

        $poe1League = $this->league('Settlers1', $poe1Id, true);
        $poe2League = $this->league('Settlers1', $poe2Id, true);

        Http::fake([
            'poe.ninja/poe1/*' => Http::response([['name' => 'Settlers1', 'id' => 'Settlers1']]),
            'poe.ninja/poe2/*' => Http::response([]),
        ]);

        $this->service()->updateAllLeaguesFromNinja();

        $this->assertDatabaseHas('poe_leagues', ['id' => $poe1League->id, 'is_active' => true]);
        $this->assertDatabaseHas('poe_leagues', ['id' => $poe2League->id, 'is_active' => false]);
    }

    public function test_it_adds_default_rules_to_active_leagues_without_a_rule(): void
    {
        config(['poe.price_check.default_hours_check_rule' => 5]);

        $poe1Id = $this->versionId('poe1');

        Http::fake([
            'poe.ninja/poe1/*' => Http::response([['name' => 'Settlers1', 'id' => 'Settlers1']]),
            'poe.ninja/poe2/*' => Http::response([]),
        ]);

        $this->service()->updateAllLeaguesFromNinja();

        $league = PoeLeague::query()
            ->where('league_name', 'Settlers1')
            ->where('poe_version_id', $poe1Id)
            ->firstOrFail();

        $this->assertDatabaseHas('league_check_rules', [
            'poe_league_id' => $league->id,
            'minutes_check' => 300,
        ]);
    }

    public function test_it_keeps_an_existing_rule_untouched(): void
    {
        config(['poe.price_check.default_hours_check_rule' => 5]);

        $poe1Id = $this->versionId('poe1');
        $league = $this->league('Settlers1', $poe1Id, true);

        LeagueCheckRule::query()->create([
            'poe_league_id' => $league->id,
            'minutes_check' => 15,
        ]);

        Http::fake([
            'poe.ninja/poe1/*' => Http::response([['name' => 'Settlers1', 'id' => 'Settlers1']]),
            'poe.ninja/poe2/*' => Http::response([]),
        ]);

        $this->service()->updateAllLeaguesFromNinja();

        $this->assertSame(1, LeagueCheckRule::query()->where('poe_league_id', $league->id)->count());
        $this->assertDatabaseHas('league_check_rules', [
            'poe_league_id' => $league->id,
            'minutes_check' => 15,
        ]);
    }

    public function test_it_does_not_add_rules_to_deactivated_leagues(): void
    {
        $poe1Id = $this->versionId('poe1');
        $league = $this->league('Old League', $poe1Id, true);

        Http::fake([
            'poe.ninja/poe1/*' => Http::response([['name' => 'Settlers1', 'id' => 'Settlers1']]),
            'poe.ninja/poe2/*' => Http::response([]),
        ]);

        $this->service()->updateAllLeaguesFromNinja();

        $this->assertDatabaseHas('poe_leagues', ['id' => $league->id, 'is_active' => false]);
        $this->assertDatabaseMissing('league_check_rules', ['poe_league_id' => $league->id]);
    }

    public function test_it_flushes_the_cache_tag_of_each_version(): void
    {
        $poe1Id = $this->versionId('poe1');
        $poe2Id = $this->versionId('poe2');

        Cache::tags([PoeLeagueTagEnum::league_version->name.$poe1Id])->put('cached', 'value', 60);
        Cache::tags([PoeLeagueTagEnum::league_version->name.$poe2Id])->put('cached', 'value', 60);

        Http::fake(['poe.ninja/*' => Http::response([])]);

        $this->service()->updateAllLeaguesFromNinja();

        $this->assertNull(Cache::tags([PoeLeagueTagEnum::league_version->name.$poe1Id])->get('cached'));
        $this->assertNull(Cache::tags([PoeLeagueTagEnum::league_version->name.$poe2Id])->get('cached'));
    }

    public function test_it_keeps_processing_other_versions_when_one_fails(): void
    {
        Log::spy();

        Http::fake([
            'poe.ninja/poe1/*' => fn () => throw new ConnectionException('Connection refused'),
            'poe.ninja/poe2/*' => Http::response([['name' => 'Standard1', 'id' => 'Standard1']]),
        ]);

        $this->service()->updateAllLeaguesFromNinja();

        $this->assertDatabaseHas('poe_leagues', [
            'league_name' => 'Standard1',
            'poe_version_id' => $this->versionId('poe2'),
            'is_active' => true,
        ]);

        Log::shouldHaveReceived('error');
    }

    public function test_it_does_not_change_leagues_when_ninja_is_unreachable_for_a_version(): void
    {
        Log::spy();

        $poe1Id = $this->versionId('poe1');
        $league = $this->league('Settlers1', $poe1Id, true);

        Http::fake([
            'poe.ninja/poe1/*' => fn () => throw new ConnectionException('Connection refused'),
            'poe.ninja/poe2/*' => Http::response([]),
        ]);

        $this->service()->updateAllLeaguesFromNinja();

        $this->assertDatabaseHas('poe_leagues', ['id' => $league->id, 'is_active' => true]);
    }

    private function service(): NinjaImportServiceContract
    {
        return $this->app->make(NinjaImportServiceContract::class);
    }

    private function versionId(string $version): int
    {
        return (int) PoeVersion::query()->where('version', $version)->value('id');
    }

    private function league(string $name, int $versionId, bool $isActive): PoeLeague
    {
        return PoeLeague::query()->create([
            'league_name' => $name,
            'ninja_league_id' => $name,
            'is_active' => $isActive,
            'poe_version_id' => $versionId,
        ]);
    }
}
