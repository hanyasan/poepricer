<?php

namespace Tests\Feature;

use App\Models\Poe\ExchangeType;
use App\Models\Poe\PoeVersion;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SyncExchangeTypesCommandTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @var list<string>
     */
    private array $temporaryFiles = [];

    public function test_it_upserts_exchange_types_for_every_poe_version(): void
    {
        $path = $this->writeExchangeTypesFile([
            'poe1' => [
                ['value' => 'Currency', 'category' => 'Currency'],
                ['value' => 'Essence', 'category' => 'Essences'],
            ],
            'poe2' => [
                ['value' => 'Runes', 'category' => 'Runes'],
            ],
        ]);

        $this->artisan('poe:sync-exchange-types', ['--path' => $path])
            ->assertSuccessful();

        $poe1Id = PoeVersion::query()->where('version', 'poe1')->value('id');
        $poe2Id = PoeVersion::query()->where('version', 'poe2')->value('id');

        $this->assertSame(2, ExchangeType::query()->where('poe_version_id', $poe1Id)->whereIn('value', ['Currency', 'Essence'])->count());
        $this->assertSame(1, ExchangeType::query()->where('poe_version_id', $poe2Id)->where('value', 'Runes')->count());

        $this->assertDatabaseHas('exchange_types', [
            'poe_version_id' => $poe1Id,
            'value' => 'Currency',
            'category' => 'Currency',
        ]);

        $this->assertDatabaseHas('exchange_types', [
            'poe_version_id' => $poe2Id,
            'value' => 'Runes',
            'category' => 'Runes',
        ]);
    }

    public function test_it_updates_category_when_the_same_value_is_synced_again(): void
    {
        $path = $this->writeExchangeTypesFile([
            'poe1' => [
                ['value' => 'Ducat', 'category' => 'Ducats'],
            ],
        ]);

        $this->artisan('poe:sync-exchange-types', ['--path' => $path])
            ->assertSuccessful();

        $this->writeExchangeTypesFile([
            'poe1' => [
                ['value' => 'Ducat', 'category' => 'Renamed Ducats'],
            ],
        ], $path);

        $this->artisan('poe:sync-exchange-types', ['--path' => $path])
            ->assertSuccessful();

        $this->assertSame(1, ExchangeType::query()->where('value', 'Ducat')->count());

        $this->assertDatabaseHas('exchange_types', [
            'value' => 'Ducat',
            'category' => 'Renamed Ducats',
        ]);
    }

    public function test_it_fails_when_the_file_does_not_exist(): void
    {
        $this->artisan('poe:sync-exchange-types', ['--path' => '/nonexistent/exchange_types.json'])
            ->assertFailed();
    }

    public function test_it_skips_versions_missing_from_poe_versions_table(): void
    {
        $path = $this->writeExchangeTypesFile([
            'poe1' => [
                ['value' => 'Currency', 'category' => 'Currency'],
            ],
            'unknown_version' => [
                ['value' => 'Whatever', 'category' => 'Whatever'],
            ],
        ]);

        $this->artisan('poe:sync-exchange-types', ['--path' => $path])
            ->expectsOutputToContain('Skipping [unknown_version]')
            ->assertSuccessful();

        $this->assertSame(0, ExchangeType::query()->where('value', 'Whatever')->count());
    }

    /**
     * @param  array<string, list<array{value: string, category: string}>>  $types
     */
    private function writeExchangeTypesFile(array $types, ?string $path = null): string
    {
        $path ??= tempnam(sys_get_temp_dir(), 'exchange_types_').'.json';

        file_put_contents($path, json_encode($types, JSON_THROW_ON_ERROR));

        $this->temporaryFiles[] = $path;

        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }
}
