<?php

namespace Tests\Feature\Repositories\Poe\Exchange\PriceCheckCore;

use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheckCoreDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheckCoreTypeDto;
use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\Exchange\ExchangeItemPrice;
use App\Models\Poe\Exchange\PriceCheckCore;
use App\Models\Poe\Exchange\PriceCheckCoreType;
use App\Models\Poe\PoeLeague;
use App\Models\Poe\PoeVersion;
use App\Repositories\Poe\Exchange\PriceCheckCore\PriceCheckCoreRepositoryContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Tests\FeatureTestCase;

class PriceCheckCoreRepositoryTest extends FeatureTestCase
{
    public function test_find_returns_the_core_with_its_types(): void
    {
        $league = $this->league('Core Find League');
        $core = $this->core($league);

        PriceCheckCoreType::query()->create([
            'rate' => 2.5,
            'exchange_item_trade_id' => 'divine',
            'name' => 'Divine Orb',
            'price_check_core_id' => $core->id,
        ]);
        PriceCheckCoreType::query()->create([
            'rate' => 1.0,
            'exchange_item_trade_id' => 'chaos',
            'name' => 'Chaos Orb',
            'price_check_core_id' => $core->id,
        ]);

        $dto = $this->repository()->find($core->id);

        $this->assertInstanceOf(PriceCheckCoreDto::class, $dto);
        $this->assertSame($core->id, $dto->id);
        $this->assertSame($league->id, $dto->poe_league_id);
        $this->assertCount(2, $dto->price_check_core_types);
        $this->assertContainsOnlyInstancesOf(PriceCheckCoreTypeDto::class, $dto->price_check_core_types);

        $divine = $this->findType($dto->price_check_core_types, 'divine');

        $this->assertSame(2.5, $divine->rate);
        $this->assertSame('Divine Orb', $divine->name);
        $this->assertSame($core->id, $divine->price_check_core_id);
    }

    public function test_find_throws_when_the_core_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository()->find(PHP_INT_MAX);
    }

    public function test_returns_the_latest_core_of_the_league(): void
    {
        $league = $this->league('Core Latest League');

        $this->core($league, '2030-01-01 10:00:00');
        $latest = $this->core($league, '2030-01-01 12:00:00');
        $this->core($league, '2030-01-01 11:00:00');

        $dto = $this->repository()->getLatestByLeague($league->id);

        $this->assertSame($latest->id, $dto->id);
        $this->assertSame($league->id, $dto->poe_league_id);
    }

    public function test_latest_core_is_scoped_to_the_league(): void
    {
        $league = $this->league('Core Latest Mine League');
        $otherLeague = $this->league('Core Latest Other League');

        $mine = $this->core($league, '2030-01-01 10:00:00');
        $this->core($otherLeague, '2030-01-01 12:00:00');

        $this->assertSame($mine->id, $this->repository()->getLatestByLeague($league->id)->id);
    }

    public function test_get_latest_by_league_throws_when_the_league_has_no_cores(): void
    {
        $league = $this->league('Core Latest Empty League');

        $this->expectException(ModelNotFoundException::class);

        $this->repository()->getLatestByLeague($league->id);
    }

    public function test_create_core_persists_the_core_and_its_types(): void
    {
        $league = $this->league('Core Create League');

        $dto = $this->repository()->createCore($league->id, [
            ['rate' => 1.0, 'exchange_item_trade_id' => 'chaos', 'name' => 'Chaos Orb'],
            ['rate' => 210.0, 'exchange_item_trade_id' => 'divine', 'name' => 'Divine Orb'],
        ]);

        $this->assertSame($league->id, $dto->poe_league_id);
        $this->assertCount(2, $dto->price_check_core_types);

        $this->assertDatabaseHas('price_check_cores', [
            'id' => $dto->id,
            'poe_league_id' => $league->id,
        ]);
        $this->assertDatabaseHas('price_check_core_types', [
            'price_check_core_id' => $dto->id,
            'exchange_item_trade_id' => 'divine',
            'name' => 'Divine Orb',
            'rate' => 210.0,
        ]);
    }

    public function test_create_core_sets_the_creation_time(): void
    {
        $league = $this->league('Core Created At League');

        $this->travelTo('2030-05-06 07:08:09');

        $dto = $this->repository()->createCore($league->id, []);

        $createdAt = PriceCheckCore::query()->whereKey($dto->id)->value('created_at');

        $this->assertSame('2030-05-06 07:08:09', Carbon::parse($createdAt)->setTimezone('UTC')->format('Y-m-d H:i:s'));
    }

    public function test_create_core_inserts_it_as_the_latest_of_the_league(): void
    {
        $league = $this->league('Core Create Latest League');

        $this->core($league, '2000-01-01 10:00:00');
        $dto = $this->repository()->createCore($league->id, []);

        $this->assertSame($dto->id, $this->repository()->getLatestByLeague($league->id)->id);
    }

    public function test_delete_many_removes_only_the_given_cores(): void
    {
        $league = $this->league('Core Delete League');

        $kept = $this->core($league, '2030-01-01 10:00:00');
        $deleted = $this->core($league, '2030-01-01 11:00:00');

        $this->repository()->deleteMany([$deleted->id]);

        $this->assertDatabaseMissing('price_check_cores', ['id' => $deleted->id]);
        $this->assertDatabaseHas('price_check_cores', ['id' => $kept->id]);
    }

    public function test_delete_many_cascades_to_core_types(): void
    {
        $league = $this->league('Core Delete Types League');
        $core = $this->core($league);

        PriceCheckCoreType::query()->create([
            'rate' => 1.0,
            'exchange_item_trade_id' => 'chaos',
            'name' => 'Chaos Orb',
            'price_check_core_id' => $core->id,
        ]);

        $this->repository()->deleteMany([$core->id]);

        $this->assertDatabaseMissing('price_check_core_types', ['price_check_core_id' => $core->id]);
    }

    public function test_delete_many_cascades_to_exchange_item_prices(): void
    {
        $league = $this->league('Core Delete Prices League');
        $core = $this->core($league);

        $item = ExchangeItem::query()->create([
            'name' => 'Chaos Orb',
            'trade_id' => 'chaos',
            'poe_league_id' => $league->id,
        ]);

        ExchangeItemPrice::query()->create([
            'rate' => 1.0,
            'exchange_item_id' => $item->id,
            'price_check_core_id' => $core->id,
        ]);

        $this->repository()->deleteMany([$core->id]);

        $this->assertDatabaseMissing('exchange_item_prices', ['price_check_core_id' => $core->id]);
    }

    public function test_delete_many_with_unknown_ids_deletes_nothing(): void
    {
        $league = $this->league('Core Delete Unknown League');
        $kept = $this->core($league);

        $this->repository()->deleteMany([PHP_INT_MAX]);

        $this->assertDatabaseHas('price_check_cores', ['id' => $kept->id]);
    }

    public function test_averages_types_rates_across_the_given_cores(): void
    {
        $league = $this->league('Core Average League');

        $older = $this->core($league, '2030-01-01 10:00:00');
        $newer = $this->core($league, '2030-01-01 12:00:00');

        $this->type($older, 'divine', 'Divine Orb', 10.0);
        $this->type($newer, 'divine', 'Divine Orb', 20.0);
        $this->type($older, 'chaos', 'Chaos Orb', 4.0);

        $result = $this->keyByTypeId($this->repository()->getAvgCoreTypesData([$older->id, $newer->id]));

        $this->assertCount(2, $result);
        $this->assertSame('Divine Orb', $result['divine']['type_name']);
        $this->assertEqualsWithDelta(15.0, (float) $result['divine']['avg_type_rate'], 0.0001);
        $this->assertSame('Chaos Orb', $result['chaos']['type_name']);
        $this->assertEqualsWithDelta(4.0, (float) $result['chaos']['avg_type_rate'], 0.0001);
    }

    public function test_averages_only_the_given_cores(): void
    {
        $league = $this->league('Core Average Scoped League');

        $mine = $this->core($league, '2030-01-01 10:00:00');
        $other = $this->core($league, '2030-01-01 12:00:00');

        $this->type($mine, 'divine', 'Divine Orb', 10.0);
        $this->type($other, 'divine', 'Divine Orb', 20.0);
        $this->type($other, 'chaos', 'Chaos Orb', 4.0);

        $result = $this->repository()->getAvgCoreTypesData([$mine->id]);

        $this->assertCount(1, $result);
        $this->assertSame('divine', $result[0]['exchange_item_trade_id']);
        $this->assertEqualsWithDelta(10.0, (float) $result[0]['avg_type_rate'], 0.0001);
    }

    public function test_averages_return_the_expected_keys(): void
    {
        $league = $this->league('Core Average Keys League');
        $core = $this->core($league);

        $this->type($core, 'divine', 'Divine Orb', 1.0);

        $result = $this->repository()->getAvgCoreTypesData([$core->id]);

        $this->assertEqualsCanonicalizing(
            ['exchange_item_trade_id', 'type_name', 'avg_type_rate'],
            array_keys($result[0]),
        );
    }

    public function test_averages_are_empty_without_types(): void
    {
        $league = $this->league('Core Average Empty League');
        $core = $this->core($league);

        $this->assertSame([], $this->repository()->getAvgCoreTypesData([$core->id]));
        $this->assertSame([], $this->repository()->getAvgCoreTypesData([]));
    }

    public function test_daily_periods_return_the_expected_keys(): void
    {
        $league = $this->league('Core Periods Keys League');
        $core = $this->core($league, '2030-01-01 10:00:00');

        $result = $this->repository()->getLeagueDailyPeriodsCoreIds(Carbon::parse('2030-01-02 00:00:00'));

        $this->assertContains($core->id, array_column($result, 'id'));
        $this->assertEqualsCanonicalizing(['id', 'poe_league_id', 'date'], array_keys($this->rowFor($result, $core->id)));
    }

    public function test_daily_periods_report_the_utc_day_of_each_core(): void
    {
        $league = $this->league('Core Periods Utc League');
        $core = $this->core($league, '2030-01-01 23:30:00+00');

        $result = $this->rowFor(
            $this->repository()->getLeagueDailyPeriodsCoreIds(Carbon::parse('2030-01-02 00:00:00')),
            $core->id,
        );

        $this->assertSame('2030-01-01', $result['date']);
        $this->assertSame($league->id, $result['poe_league_id']);
    }

    public function test_daily_periods_include_only_cores_up_to_the_given_date(): void
    {
        $league = $this->league('Core Periods Range League');

        $early = $this->core($league, '2030-01-01 10:00:00+00');
        $late = $this->core($league, '2030-01-05 10:00:00+00');

        $ids = array_column(
            $this->repository()->getLeagueDailyPeriodsCoreIds(Carbon::parse('2030-01-02 00:00:00')),
            'id',
        );

        $this->assertContains($early->id, $ids);
        $this->assertNotContains($late->id, $ids);
    }

    public function test_daily_periods_cover_every_league(): void
    {
        $league = $this->league('Core Periods League One');
        $otherLeague = $this->league('Core Periods League Two');

        $first = $this->core($league, '2030-01-01 10:00:00+00');
        $second = $this->core($otherLeague, '2030-01-01 11:00:00+00');

        $ids = array_column(
            $this->repository()->getLeagueDailyPeriodsCoreIds(Carbon::parse('2030-01-02 00:00:00')),
            'id',
        );

        $this->assertContains($first->id, $ids);
        $this->assertContains($second->id, $ids);
    }

    private function repository(): PriceCheckCoreRepositoryContract
    {
        return $this->app->make(PriceCheckCoreRepositoryContract::class);
    }

    private function league(string $name): PoeLeague
    {
        return PoeLeague::query()->create([
            'league_name' => $name,
            'is_active' => true,
            'poe_version_id' => (int) PoeVersion::query()->where('version', 'poe2')->value('id'),
        ]);
    }

    private function core(PoeLeague $league, string $createdAt = '2030-01-01 10:00:00'): PriceCheckCore
    {
        return PriceCheckCore::query()->create([
            'poe_league_id' => $league->id,
            'created_at' => $createdAt,
        ]);
    }

    private function type(PriceCheckCore $core, string $tradeId, string $name, float $rate): PriceCheckCoreType
    {
        return PriceCheckCoreType::query()->create([
            'rate' => $rate,
            'exchange_item_trade_id' => $tradeId,
            'name' => $name,
            'price_check_core_id' => $core->id,
        ]);
    }

    /**
     * @param  PriceCheckCoreTypeDto[]  $types
     */
    private function findType(array $types, string $tradeId): PriceCheckCoreTypeDto
    {
        foreach ($types as $type) {
            if ($type->exchange_item_trade_id === $tradeId) {
                return $type;
            }
        }

        $this->fail("No price check core type dto with trade id [{$tradeId}] was returned.");
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function keyByTypeId(array $rows): array
    {
        $keyed = [];

        foreach ($rows as $row) {
            $keyed[(string) $row['exchange_item_trade_id']] = $row;
        }

        return $keyed;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function rowFor(array $rows, int $id): array
    {
        foreach ($rows as $row) {
            if ((int) $row['id'] === $id) {
                return $row;
            }
        }

        $this->fail("No daily period row with core id [{$id}] was returned.");
    }
}
