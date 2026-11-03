<?php

namespace Tests\Feature;

use App\Models\Poe\PoeVersion;
use App\Services\NinjaImport\NinjaImportServiceContract;
use Illuminate\Support\Facades\Http;
use Tests\FeatureTestCase;

class UpdateLeaguesFromNinjaCommandFeatureTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_it_updates_leagues_from_ninja(): void
    {
        $poe1Id = $this->versionId('poe1');

        Http::fake([
            'poe.ninja/poe1/*' => Http::response([['name' => 'Settlers', 'id' => 'settlers']]),
            'poe.ninja/poe2/*' => Http::response([]),
        ]);

        $this->artisan('poe:update-leagues-from-ninja')
            ->expectsOutputToContain('Updating leagues from poe.ninja')
            ->expectsOutputToContain('Leagues updated')
            ->assertSuccessful();

        $this->assertDatabaseHas('poe_leagues', [
            'league_name' => 'Settlers',
            'poe_version_id' => $poe1Id,
            'is_active' => true,
        ]);
    }

    public function test_it_calls_the_import_service_once(): void
    {
        $service = $this->mock(NinjaImportServiceContract::class);
        $service->expects('updateAllLeaguesFromNinja')->once();

        $this->artisan('poe:update-leagues-from-ninja')
            ->assertSuccessful();
    }

    private function versionId(string $version): int
    {
        return (int) PoeVersion::query()->where('version', $version)->value('id');
    }
}
