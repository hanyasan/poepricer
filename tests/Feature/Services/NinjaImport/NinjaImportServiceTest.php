<?php

namespace Tests\Feature\Services\NinjaImport;

use App\DataTransferObjects\RepositoryObjects\League\ExtendedPoeLeagueDto;
use App\Enums\Tags\PoeLeagueTagEnum;
use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\Exchange\ExchangeItemPrice;
use App\Models\Poe\Exchange\ExchangeType;
use App\Models\Poe\Exchange\Metrics\ExchangeMetricsItem;
use App\Models\Poe\Exchange\PriceCheck;
use App\Models\Poe\Exchange\PriceCheckCore;
use App\Models\Poe\Exchange\PriceCheckCoreType;
use App\Models\Poe\LeagueCheckRule;
use App\Models\Poe\PoeLeague;
use App\Models\Poe\PoeVersion;
use App\Repositories\Poe\PoeLeagueRepositoryContract;
use App\Services\NinjaImport\NinjaImportServiceContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
            'poe.ninja/poe1/*' => Http::response([['name' => 'League One', 'id' => 'League One']]),
            'poe.ninja/poe2/*' => Http::response([['name' => 'League Two', 'id' => 'League Two']]),
        ]);

        $this->service()->updateAllLeaguesFromNinja();

        $this->assertDatabaseHas('poe_leagues', [
            'league_name' => 'League One',
            'ninja_league_id' => 'League One',
            'poe_version_id' => $poe1Id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('poe_leagues', [
            'league_name' => 'League Two',
            'ninja_league_id' => 'League Two',
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
            'poe.ninja/poe1/*' => Http::response([['name' => 'League One', 'id' => 'League One']]),
            'poe.ninja/poe2/*' => Http::response([]),
        ]);

        $this->service()->updateAllLeaguesFromNinja();

        $this->assertDatabaseHas('poe_leagues', ['id' => $league->id, 'is_active' => false]);
        $this->assertDatabaseHas('poe_leagues', [
            'league_name' => 'League One',
            'poe_version_id' => $poe1Id,
            'is_active' => true,
        ]);
    }

    public function test_it_keeps_a_listed_league_active_without_duplicating_it(): void
    {
        $poe1Id = $this->versionId('poe1');
        $league = $this->league('League One', $poe1Id, true);

        Http::fake([
            'poe.ninja/poe1/*' => Http::response([['name' => 'League One', 'id' => 'League One']]),
            'poe.ninja/poe2/*' => Http::response([]),
        ]);

        $this->service()->updateAllLeaguesFromNinja();

        $this->assertSame(
            1,
            PoeLeague::query()->where('league_name', 'League One')->where('poe_version_id', $poe1Id)->count(),
        );
        $this->assertDatabaseHas('poe_leagues', ['id' => $league->id, 'is_active' => true]);
    }

    public function test_it_reactivates_a_league_that_ninja_lists_again(): void
    {
        $poe1Id = $this->versionId('poe1');
        $league = $this->league('League One', $poe1Id, false);

        Http::fake([
            'poe.ninja/poe1/*' => Http::response([['name' => 'League One', 'id' => 'League One']]),
            'poe.ninja/poe2/*' => Http::response([]),
        ]);

        $this->service()->updateAllLeaguesFromNinja();

        $this->assertDatabaseHas('poe_leagues', ['id' => $league->id, 'is_active' => true]);
    }

    public function test_it_does_not_deactivate_leagues_of_another_version(): void
    {
        $poe1Id = $this->versionId('poe1');
        $poe2Id = $this->versionId('poe2');

        $poe2League = $this->league('League Two', $poe2Id, true);

        Http::fake([
            'poe.ninja/poe1/*' => Http::response([['name' => 'League One', 'id' => 'League One']]),
            'poe.ninja/poe2/*' => Http::response([['name' => 'League Two', 'id' => 'League Two']]),
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

        $poe1League = $this->league('League One', $poe1Id, true);
        $poe2League = $this->league('League One', $poe2Id, true);

        Http::fake([
            'poe.ninja/poe1/*' => Http::response([['name' => 'League One', 'id' => 'League One']]),
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
            'poe.ninja/poe1/*' => Http::response([['name' => 'League One', 'id' => 'League One']]),
            'poe.ninja/poe2/*' => Http::response([]),
        ]);

        $this->service()->updateAllLeaguesFromNinja();

        $league = PoeLeague::query()
            ->where('league_name', 'League One')
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
        $league = $this->league('League One', $poe1Id, true);

        LeagueCheckRule::query()->create([
            'poe_league_id' => $league->id,
            'minutes_check' => 15,
        ]);

        Http::fake([
            'poe.ninja/poe1/*' => Http::response([['name' => 'League One', 'id' => 'League One']]),
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
            'poe.ninja/poe1/*' => Http::response([['name' => 'League One', 'id' => 'League One']]),
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
            'poe.ninja/poe2/*' => Http::response([['name' => 'League Two', 'id' => 'League Two']]),
        ]);

        $this->service()->updateAllLeaguesFromNinja();

        $this->assertDatabaseHas('poe_leagues', [
            'league_name' => 'League Two',
            'poe_version_id' => $this->versionId('poe2'),
            'is_active' => true,
        ]);

        Log::shouldHaveReceived('error');
    }

    public function test_it_does_not_change_leagues_when_ninja_is_unreachable_for_a_version(): void
    {
        Log::spy();

        $poe1Id = $this->versionId('poe1');
        $league = $this->league('League One', $poe1Id, true);

        Http::fake([
            'poe.ninja/poe1/*' => fn () => throw new ConnectionException('Connection refused'),
            'poe.ninja/poe2/*' => Http::response([]),
        ]);

        $this->service()->updateAllLeaguesFromNinja();

        $this->assertDatabaseHas('poe_leagues', ['id' => $league->id, 'is_active' => true]);
    }

    public function test_it_creates_one_price_check_for_the_league(): void
    {
        $version = $this->versionRow('poe2');
        $league = $this->importLeague('League Two', $version);
        $this->exchangeType('type-alpha', $version);

        $this->fakeExchangePrices(fn (): array => $this->exchangePayload());

        $this->service()->checkExchangePrices($this->leagueDto($league));

        $this->assertSame(1, PriceCheck::query()->where('poe_league_id', $league->id)->count());
    }

    public function test_it_requests_prices_for_each_exchange_type_of_the_version(): void
    {
        $version = $this->versionRow('poe2');
        $league = $this->importLeague('League Two', $version);
        $typeAlpha = $this->exchangeType('type-alpha', $version);
        $typeBeta = $this->exchangeType('type-beta', $version);

        $this->fakeExchangePrices(fn (): array => $this->exchangePayload());

        $this->service()->checkExchangePrices($this->leagueDto($league));

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->url()
            === 'https://poe.ninja/poe2/api/economy/exchange/current/overview?league=league-two&type='.$typeAlpha->value);
        Http::assertSent(fn (Request $request): bool => $request->url()
            === 'https://poe.ninja/poe2/api/economy/exchange/current/overview?league=league-two&type='.$typeBeta->value);
    }

    public function test_it_does_not_request_prices_for_exchange_types_of_another_version(): void
    {
        $version = $this->versionRow('poe2');
        $otherVersion = $this->versionRow('poe1');
        $league = $this->importLeague('League Two', $version);
        $this->exchangeType('type-alpha', $otherVersion);
        $typeBeta = $this->exchangeType('type-beta', $version);

        $this->fakeExchangePrices(fn (): array => $this->exchangePayload());

        $this->service()->checkExchangePrices($this->leagueDto($league));

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url()
            === 'https://poe.ninja/poe2/api/economy/exchange/current/overview?league=league-two&type='.$typeBeta->value);
    }

    public function test_it_persists_the_core_types_ninja_reports(): void
    {
        $version = $this->versionRow('poe2');
        $league = $this->importLeague('League Two', $version);
        $this->exchangeType('type-alpha', $version);

        $this->fakeExchangePrices(fn (): array => $this->exchangePayload(
            coreItems: [
                ['id' => 'item-a', 'name' => 'Item A', 'category' => 'type-alpha'],
                ['id' => 'item-b', 'name' => 'Item B', 'category' => 'type-alpha'],
            ],
            rates: ['item-a' => 1.0, 'item-b' => 210.5],
        ));

        $this->service()->checkExchangePrices($this->leagueDto($league));

        $coreId = $this->coreIdOf($league);

        $this->assertDatabaseHas('price_check_core_types', [
            'exchange_item_trade_id' => 'item-a',
            'name' => 'Item A',
            'rate' => 1.0,
            'price_check_core_id' => $coreId,
        ]);
        $this->assertDatabaseHas('price_check_core_types', [
            'exchange_item_trade_id' => 'item-b',
            'name' => 'Item B',
            'rate' => 210.5,
            'price_check_core_id' => $coreId,
        ]);
    }

    public function test_it_defaults_a_missing_core_rate_to_one(): void
    {
        $version = $this->versionRow('poe2');
        $league = $this->importLeague('League Two', $version);
        $this->exchangeType('type-alpha', $version);

        $this->fakeExchangePrices(fn (): array => $this->exchangePayload(
            coreItems: [['id' => 'item-b', 'name' => 'Item B', 'category' => 'type-alpha']],
            rates: ['item-a' => 1.0],
        ));

        $this->service()->checkExchangePrices($this->leagueDto($league));

        $this->assertDatabaseHas('price_check_core_types', [
            'exchange_item_trade_id' => 'item-b',
            'rate' => 1.0,
            'price_check_core_id' => $this->coreIdOf($league),
        ]);
    }

    public function test_it_links_each_core_to_its_exchange_type(): void
    {
        $version = $this->versionRow('poe2');
        $league = $this->importLeague('League Two', $version);
        $typeAlpha = $this->exchangeType('type-alpha', $version);
        $typeBeta = $this->exchangeType('type-beta', $version);

        $this->fakeExchangePrices(fn (): array => $this->exchangePayload(
            coreItems: [['id' => 'item-a', 'name' => 'Item A', 'category' => 'type-alpha']],
            rates: ['item-a' => 1.0],
        ));

        $this->service()->checkExchangePrices($this->leagueDto($league));

        $priceCheckId = $this->priceCheckIdOf($league);

        $this->assertSame(2, PriceCheckCore::query()->where('price_check_id', $priceCheckId)->count());
        $this->assertDatabaseHas('price_check_cores', [
            'price_check_id' => $priceCheckId,
            'exchange_type_id' => $typeAlpha->id,
        ]);
        $this->assertDatabaseHas('price_check_cores', [
            'price_check_id' => $priceCheckId,
            'exchange_type_id' => $typeBeta->id,
        ]);
    }

    public function test_it_persists_exchange_items_for_the_league(): void
    {
        $version = $this->versionRow('poe2');
        $league = $this->importLeague('League Two', $version);
        $this->exchangeType('type-alpha', $version);

        $this->fakeExchangePrices(fn (): array => $this->exchangePayload(
            items: [['id' => 'item-b', 'name' => 'Item B', 'category' => 'type-alpha']],
        ));

        $this->service()->checkExchangePrices($this->leagueDto($league));

        $this->assertDatabaseHas('exchange_items', [
            'name' => 'Item B',
            'trade_id' => 'item-b',
            'poe_category' => 'type-alpha',
            'poe_league_id' => $league->id,
        ]);
    }

    public function test_it_persists_a_price_from_the_matching_line(): void
    {
        $version = $this->versionRow('poe2');
        $league = $this->importLeague('League Two', $version);
        $this->exchangeType('type-alpha', $version);

        $this->fakeExchangePrices(fn (): array => $this->exchangePayload(
            lines: ['item-b' => 210.5],
            items: [['id' => 'item-b', 'name' => 'Item B', 'category' => 'type-alpha']],
        ));

        $this->service()->checkExchangePrices($this->leagueDto($league));

        $coreId = $this->coreIdOf($league);
        $itemId = (int) ExchangeItem::query()
            ->where('trade_id', 'item-b')
            ->where('poe_league_id', $league->id)
            ->value('id');

        $this->assertSame(
            [210.5],
            ExchangeItemPrice::query()->where('price_check_core_id', $coreId)->pluck('rate')->all(),
        );
        $this->assertDatabaseHas('exchange_item_prices', [
            'exchange_item_id' => $itemId,
            'price_check_core_id' => $coreId,
        ]);
    }

    public function test_it_persists_the_traded_volume_of_a_price(): void
    {
        $version = $this->versionRow('poe2');
        $league = $this->importLeague('League Two', $version);
        $this->exchangeType('type-alpha', $version);

        $this->fakeExchangePrices(fn (): array => $this->exchangePayload(
            lines: ['item-b' => 210.5],
            items: [['id' => 'item-b', 'name' => 'Item B', 'category' => 'type-alpha']],
            volumes: ['item-b' => 37.25],
        ));

        $this->service()->checkExchangePrices($this->leagueDto($league));

        $this->assertSame(
            [37.25],
            ExchangeItemPrice::query()
                ->where('price_check_core_id', $this->coreIdOf($league))
                ->pluck('volume')
                ->all(),
        );
    }

    public function test_it_persists_a_price_without_volume_as_null(): void
    {
        $version = $this->versionRow('poe2');
        $league = $this->importLeague('League Two', $version);
        $this->exchangeType('type-alpha', $version);

        $this->fakeExchangePrices(fn (): array => $this->exchangePayload(
            lines: ['item-b' => 210.5],
            items: [['id' => 'item-b', 'name' => 'Item B', 'category' => 'type-alpha']],
        ));

        $this->service()->checkExchangePrices($this->leagueDto($league));

        $this->assertSame(
            [null],
            ExchangeItemPrice::query()
                ->where('price_check_core_id', $this->coreIdOf($league))
                ->pluck('volume')
                ->all(),
        );
    }

    public function test_it_mixes_the_metrics_currencies_into_every_core_of_the_price_check(): void
    {
        $version = $this->versionRow('poe2');
        $league = $this->importLeague('League Two', $version);
        $this->exchangeType('type-alpha', $version);
        $this->exchangeType('type-beta', $version);

        ExchangeMetricsItem::factory()
            ->forLeagueCurrency($league, $this->exchangeItem($league, 'mirror', 'Mirror'))
            ->create();

        $this->fakeExchangePrices(fn (): array => $this->exchangePayload(
            coreItems: [['id' => 'divine', 'name' => 'Divine Orb', 'category' => 'type-alpha']],
            rates: ['exalted' => 600.0],
            lines: ['mirror' => 4000.0],
            items: [['id' => 'mirror', 'name' => 'Mirror', 'category' => 'type-alpha']],
        ));

        $this->service()->checkExchangePrices($this->leagueDto($league));

        $cores = PriceCheckCore::query()->where('price_check_id', $this->priceCheckIdOf($league))->get();

        $this->assertCount(2, $cores);

        foreach ($cores as $core) {
            $this->assertSame(
                [1 / 4000.0],
                PriceCheckCoreType::query()
                    ->where('price_check_core_id', $core->id)
                    ->where('exchange_item_trade_id', 'mirror')
                    ->pluck('rate')
                    ->all(),
            );
        }
    }

    public function test_it_mixes_nothing_without_metrics_items(): void
    {
        $version = $this->versionRow('poe2');
        $league = $this->importLeague('League Two', $version);
        $this->exchangeType('type-alpha', $version);

        $this->fakeExchangePrices(fn (): array => $this->exchangePayload(
            lines: ['mirror' => 4000.0],
            items: [['id' => 'mirror', 'name' => 'Mirror', 'category' => 'type-alpha']],
        ));

        $this->service()->checkExchangePrices($this->leagueDto($league));

        $this->assertSame(
            0,
            PriceCheckCoreType::query()
                ->where('exchange_item_trade_id', 'mirror')
                ->where('price_check_core_id', $this->coreIdOf($league))
                ->count(),
        );
    }

    public function test_it_skips_items_without_a_matching_line(): void
    {
        $version = $this->versionRow('poe2');
        $league = $this->importLeague('League Two', $version);
        $this->exchangeType('type-alpha', $version);

        $this->fakeExchangePrices(fn (): array => $this->exchangePayload(
            lines: ['item-c' => 12.5],
            items: [['id' => 'item-b', 'name' => 'Item B', 'category' => 'type-alpha']],
        ));

        $this->service()->checkExchangePrices($this->leagueDto($league));

        $this->assertDatabaseHas('exchange_items', [
            'trade_id' => 'item-b',
            'poe_league_id' => $league->id,
        ]);
        $this->assertSame(0, $this->priceCountOf($league));
    }

    public function test_it_skips_items_whose_line_has_no_primary_value(): void
    {
        $version = $this->versionRow('poe2');
        $league = $this->importLeague('League Two', $version);
        $this->exchangeType('type-alpha', $version);

        $this->fakeExchangePrices(fn (): array => $this->exchangePayload(
            lines: ['item-b' => null],
            items: [['id' => 'item-b', 'name' => 'Item B', 'category' => 'type-alpha']],
        ));

        $this->service()->checkExchangePrices($this->leagueDto($league));

        $this->assertDatabaseHas('exchange_items', [
            'trade_id' => 'item-b',
            'poe_league_id' => $league->id,
        ]);
        $this->assertSame(0, $this->priceCountOf($league));
    }

    public function test_it_does_not_price_items_of_another_league(): void
    {
        $version = $this->versionRow('poe2');
        $league = $this->importLeague('League Two', $version);
        $otherLeague = $this->importLeague('League Three', $version, 'league-three');
        $this->exchangeType('type-alpha', $version);

        $foreignItem = $this->exchangeItem($otherLeague, 'item-c', 'Item C');

        $this->fakeExchangePrices(fn (): array => $this->exchangePayload(
            lines: ['item-b' => 210.5, 'item-c' => 12.5],
            items: [['id' => 'item-b', 'name' => 'Item B', 'category' => 'type-alpha']],
        ));

        $this->service()->checkExchangePrices($this->leagueDto($league));

        $this->assertDatabaseMissing('exchange_item_prices', ['exchange_item_id' => $foreignItem->id]);
        $this->assertSame(1, $this->priceCountOf($league));
    }

    public function test_it_propagates_the_request_error_when_ninja_fails(): void
    {
        $version = $this->versionRow('poe2');
        $league = $this->importLeague('League Two', $version);
        $this->exchangeType('type-alpha', $version);

        Http::fake([
            'poe.ninja/poe2/api/economy/exchange/current/overview*' => Http::response(['error' => 'boom'], 500),
        ]);
        $this->expectException(RequestException::class);

        $this->service()->checkExchangePrices($this->leagueDto($league));
    }

    /**
     * @param  callable(): array<string, mixed>  $payload
     */
    private function fakeExchangePrices(callable $payload): void
    {
        Http::fake([
            'poe.ninja/poe2/api/economy/exchange/current/overview*' => fn (): mixed => Http::response($payload()),
        ]);
    }

    /**
     * @param  list<array{id: string, name: string, category: string}>  $coreItems
     * @param  array<string, float>  $rates
     * @param  array<string, float|null>  $lines
     * @param  list<array{id: string, name: string, category: string}>  $items
     * @param  array<string, float>  $volumes
     * @return array<string, mixed>
     */
    private function exchangePayload(
        array $coreItems = [],
        array $rates = [],
        array $lines = [],
        array $items = [],
        array $volumes = [],
    ): array {
        return [
            'core' => [
                'items' => $coreItems,
                'rates' => $rates,
            ],
            'lines' => array_map(
                static fn (string $id, ?float $primaryValue): array => [
                    'id' => $id,
                    'primaryValue' => $primaryValue,
                    'volumePrimaryValue' => $volumes[$id] ?? null,
                ],
                array_keys($lines),
                $lines,
            ),
            'items' => $items,
        ];
    }

    private function leagueDto(PoeLeague $league): ExtendedPoeLeagueDto
    {
        $dtos = $this->app->make(PoeLeagueRepositoryContract::class)
            ->getWithLastPriceCheckAndRule($league->poe_version_id);

        foreach ($dtos as $dto) {
            if ($dto->id === $league->id) {
                return $dto;
            }
        }

        $this->fail("No extended league dto with id [{$league->id}] was returned.");
    }

    private function priceCheckIdOf(PoeLeague $league): int
    {
        return (int) PriceCheck::query()->where('poe_league_id', $league->id)->value('id');
    }

    private function coreIdOf(PoeLeague $league): int
    {
        return (int) PriceCheckCore::query()
            ->where('price_check_id', $this->priceCheckIdOf($league))
            ->value('id');
    }

    private function priceCountOf(PoeLeague $league): int
    {
        return ExchangeItemPrice::query()
            ->whereIn('exchange_item_id', ExchangeItem::query()
                ->where('poe_league_id', $league->id)
                ->select('id'))
            ->count();
    }

    /**
     * Each test owns its own version row, so the exchange types that the import reads are
     * only the ones the test created. The version string stays a real one because it
     * becomes the path segment of the poe.ninja request.
     */
    private function versionRow(string $version): PoeVersion
    {
        $id = (int) DB::table('poe_versions')->max('id') + 1;

        DB::table('poe_versions')->insert(['id' => $id, 'version' => $version]);

        return PoeVersion::query()->findOrFail($id);
    }

    private function importLeague(
        string $name,
        PoeVersion $version,
        string $ninjaLeagueId = 'league-two',
    ): PoeLeague {
        return PoeLeague::query()->create([
            'league_name' => $name,
            'ninja_league_id' => $ninjaLeagueId,
            'is_active' => true,
            'poe_version_id' => $version->id,
        ]);
    }

    private function exchangeType(string $value, PoeVersion $version): ExchangeType
    {
        return ExchangeType::query()->create([
            'category' => $value,
            'value' => $value,
            'poe_version_id' => $version->id,
        ]);
    }

    private function exchangeItem(PoeLeague $league, string $tradeId, string $name): ExchangeItem
    {
        return ExchangeItem::query()->create([
            'name' => $name,
            'trade_id' => $tradeId,
            'poe_category' => 'type-alpha',
            'poe_league_id' => $league->id,
        ]);
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
