<?php

namespace Tests\Feature\Repositories\Poe\Exchange\PriceCheck;

use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheckCoreDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheckCoreTypeDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheckDto;
use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\Exchange\ExchangeItemPrice;
use App\Models\Poe\Exchange\ExchangeType;
use App\Models\Poe\Exchange\PriceCheck;
use App\Models\Poe\Exchange\PriceCheckCore;
use App\Models\Poe\Exchange\PriceCheckCoreType;
use App\Models\Poe\PoeLeague;
use App\Models\Poe\PoeVersion;
use App\Repositories\Poe\Exchange\PriceCheck\PriceCheckRepositoryContract;
use Illuminate\Support\Carbon;
use Tests\FeatureTestCase;

class PriceCheckRepositoryTest extends FeatureTestCase
{
    public function test_create_price_check_persists_the_row(): void
    {
        $league = $this->league('Create Price Check League');

        $dto = $this->repository()->createPriceCheck($league->id);

        $this->assertInstanceOf(PriceCheckDto::class, $dto);
        $this->assertSame($league->id, $dto->poe_league_id);
        $this->assertDatabaseHas('price_checks', [
            'id' => $dto->id,
            'poe_league_id' => $league->id,
        ]);
    }

    public function test_create_price_check_sets_the_creation_time(): void
    {
        $league = $this->league('Create Price Check Time League');

        $this->travelTo('2030-05-06 07:08:09');

        $dto = $this->repository()->createPriceCheck($league->id);

        $this->assertSame('2030-05-06 07:08:09', $this->utcTimestamp($dto->created_at));
        $this->assertSame(
            '2030-05-06 07:08:09',
            $this->utcTimestamp((string) PriceCheck::query()->whereKey($dto->id)->value('created_at')),
        );
    }

    public function test_create_core_persists_the_core_and_its_types(): void
    {
        $league = $this->league('Create Core League');
        $priceCheck = $this->priceCheck($league);
        $exchangeType = $this->exchangeType('Currency');

        $dto = $this->repository()->createCore($priceCheck->id, $exchangeType->id, [
            ['rate' => 1.0, 'exchange_item_trade_id' => 'chaos', 'name' => 'Chaos Orb'],
            ['rate' => 210.0, 'exchange_item_trade_id' => 'divine', 'name' => 'Divine Orb'],
        ]);

        $this->assertInstanceOf(PriceCheckCoreDto::class, $dto);
        $this->assertSame($priceCheck->id, $dto->price_check_id);
        $this->assertSame($exchangeType->id, $dto->exchange_type_id);
        $this->assertDatabaseHas('price_check_cores', [
            'id' => $dto->id,
            'price_check_id' => $priceCheck->id,
            'exchange_type_id' => $exchangeType->id,
        ]);
        $this->assertSame(
            2,
            PriceCheckCoreType::query()->where('price_check_core_id', $dto->id)->count(),
        );
    }

    public function test_create_core_returns_its_types_as_dtos(): void
    {
        $league = $this->league('Create Core Types League');
        $priceCheck = $this->priceCheck($league);
        $exchangeType = $this->exchangeType('Currency');

        $dto = $this->repository()->createCore($priceCheck->id, $exchangeType->id, [
            ['rate' => 1.0, 'exchange_item_trade_id' => 'chaos', 'name' => 'Chaos Orb'],
            ['rate' => 210.0, 'exchange_item_trade_id' => 'divine', 'name' => 'Divine Orb'],
        ]);

        $this->assertCount(2, $dto->price_check_core_types);
        $this->assertContainsOnlyInstancesOf(PriceCheckCoreTypeDto::class, $dto->price_check_core_types);

        $divine = $this->findType($dto->price_check_core_types, 'divine');

        $this->assertSame(210.0, $divine->rate);
        $this->assertSame('Divine Orb', $divine->name);
        $this->assertSame($dto->id, $divine->price_check_core_id);
    }

    public function test_create_core_without_types_persists_only_the_core(): void
    {
        $league = $this->league('Create Empty Core League');
        $priceCheck = $this->priceCheck($league);

        $dto = $this->repository()->createCore($priceCheck->id, $this->exchangeType('Runes')->id, []);

        $this->assertSame([], $dto->price_check_core_types);
        $this->assertDatabaseHas('price_check_cores', ['id' => $dto->id]);
        $this->assertSame(
            0,
            PriceCheckCoreType::query()->where('price_check_core_id', $dto->id)->count(),
        );
    }

    public function test_create_core_keeps_cores_of_different_exchange_types_apart(): void
    {
        $league = $this->league('Two Exchange Types League');
        $priceCheck = $this->priceCheck($league);

        $currency = $this->repository()->createCore(
            $priceCheck->id,
            $this->exchangeType('Currency')->id,
            [],
        );
        $runes = $this->repository()->createCore(
            $priceCheck->id,
            $this->exchangeType('Runes')->id,
            [],
        );

        $this->assertNotSame($currency->id, $runes->id);
        $this->assertSame(2, PriceCheckCore::query()->where('price_check_id', $priceCheck->id)->count());
    }

    public function test_create_core_does_not_persist_the_core_when_the_types_fail(): void
    {
        $league = $this->league('Failing Core League');
        $priceCheck = $this->priceCheck($league);
        $exchangeType = $this->exchangeType('Currency');

        try {
            $this->repository()->createCore($priceCheck->id, $exchangeType->id, [
                ['rate' => 1.0, 'exchange_item_trade_id' => 'chaos', 'name' => 'Chaos Orb'],
                ['rate' => 1.0, 'name' => 'Missing trade id'],
            ]);
        } catch (\Throwable) {
            // the transaction must roll the core back
        }

        $this->assertSame(0, PriceCheckCore::query()->where('price_check_id', $priceCheck->id)->count());
    }

    public function test_insert_prices_persists_every_row(): void
    {
        $league = $this->league('Insert Prices League');
        $priceCheck = $this->priceCheck($league);
        $core = $this->core($priceCheck, $this->exchangeType('Currency'));

        $divine = $this->item($league, 'divine', 'Divine Orb');
        $chaos = $this->item($league, 'chaos', 'Chaos Orb');

        $this->repository()->insertPrices([
            ['rate' => 1.0, 'price_check_core_id' => $core->id, 'exchange_item_id' => $divine->id],
            ['rate' => 0.25, 'price_check_core_id' => $core->id, 'exchange_item_id' => $chaos->id],
        ]);

        $this->assertSame(2, ExchangeItemPrice::query()->where('price_check_core_id', $core->id)->count());
        $this->assertDatabaseHas('exchange_item_prices', [
            'rate' => 1.0,
            'price_check_core_id' => $core->id,
            'exchange_item_id' => $divine->id,
        ]);
        $this->assertDatabaseHas('exchange_item_prices', [
            'rate' => 0.25,
            'price_check_core_id' => $core->id,
            'exchange_item_id' => $chaos->id,
        ]);
    }

    public function test_insert_prices_keeps_cores_apart(): void
    {
        $league = $this->league('Insert Prices Two Cores League');
        $priceCheck = $this->priceCheck($league);

        $currency = $this->core($priceCheck, $this->exchangeType('Currency'));
        $runes = $this->core($priceCheck, $this->exchangeType('Runes'));

        $divine = $this->item($league, 'divine', 'Divine Orb');

        $this->repository()->insertPrices([
            ['rate' => 10.0, 'price_check_core_id' => $currency->id, 'exchange_item_id' => $divine->id],
            ['rate' => 12.0, 'price_check_core_id' => $runes->id, 'exchange_item_id' => $divine->id],
        ]);

        $this->assertSame(
            [10.0],
            ExchangeItemPrice::query()->where('price_check_core_id', $currency->id)->pluck('rate')->all(),
        );
        $this->assertSame(
            [12.0],
            ExchangeItemPrice::query()->where('price_check_core_id', $runes->id)->pluck('rate')->all(),
        );
    }

    public function test_insert_prices_with_no_rows_persists_nothing(): void
    {
        $before = ExchangeItemPrice::query()->count();

        $this->repository()->insertPrices([]);

        $this->assertSame($before, ExchangeItemPrice::query()->count());
    }

    public function test_delete_many_removes_only_the_given_rows(): void
    {
        $league = $this->league('Delete League');

        $kept = $this->priceCheck($league);
        $deleted = $this->priceCheck($league);

        $this->repository()->deleteMany([$deleted->id]);

        $this->assertDatabaseMissing('price_checks', ['id' => $deleted->id]);
        $this->assertDatabaseHas('price_checks', ['id' => $kept->id]);
    }

    public function test_delete_many_cascades_to_cores(): void
    {
        $league = $this->league('Delete Cores League');
        $priceCheck = $this->priceCheck($league);
        $core = $this->core($priceCheck, $this->exchangeType('Currency'));

        $this->repository()->deleteMany([$priceCheck->id]);

        $this->assertDatabaseMissing('price_check_cores', ['id' => $core->id]);
    }

    public function test_delete_many_cascades_to_core_types(): void
    {
        $league = $this->league('Delete Core Types League');
        $priceCheck = $this->priceCheck($league);
        $core = $this->core($priceCheck, $this->exchangeType('Currency'));

        PriceCheckCoreType::query()->create([
            'rate' => 1.0,
            'exchange_item_trade_id' => 'chaos',
            'name' => 'Chaos Orb',
            'price_check_core_id' => $core->id,
        ]);

        $this->repository()->deleteMany([$priceCheck->id]);

        $this->assertDatabaseMissing('price_check_core_types', ['price_check_core_id' => $core->id]);
    }

    public function test_delete_many_with_unknown_ids_deletes_nothing(): void
    {
        $league = $this->league('Delete Unknown League');
        $kept = $this->priceCheck($league);

        $this->repository()->deleteMany([PHP_INT_MAX]);

        $this->assertDatabaseHas('price_checks', ['id' => $kept->id]);
    }

    private function repository(): PriceCheckRepositoryContract
    {
        return $this->app->make(PriceCheckRepositoryContract::class);
    }

    private function league(string $name): PoeLeague
    {
        return PoeLeague::query()->create([
            'league_name' => $name,
            'ninja_league_id' => 'ninja-'.strtolower(str_replace(' ', '-', $name)),
            'is_active' => true,
            'poe_version_id' => (int) PoeVersion::query()->where('version', 'poe2')->value('id'),
        ]);
    }

    private function exchangeType(string $value): ExchangeType
    {
        return ExchangeType::query()->create([
            'category' => $value,
            'value' => $value,
            'poe_version_id' => (int) PoeVersion::query()->where('version', 'poe2')->value('id'),
        ]);
    }

    private function priceCheck(PoeLeague $league, string $createdAt = '2030-01-01 10:00:00'): PriceCheck
    {
        return PriceCheck::query()->create([
            'poe_league_id' => $league->id,
            'created_at' => $createdAt,
        ]);
    }

    private function core(PriceCheck $priceCheck, ExchangeType $exchangeType): PriceCheckCore
    {
        return PriceCheckCore::query()->create([
            'price_check_id' => $priceCheck->id,
            'exchange_type_id' => $exchangeType->id,
        ]);
    }

    private function item(PoeLeague $league, string $tradeId, string $name): ExchangeItem
    {
        return ExchangeItem::query()->create([
            'name' => $name,
            'trade_id' => $tradeId,
            'poe_league_id' => $league->id,
        ]);
    }

    private function utcTimestamp(string $value): string
    {
        return Carbon::parse($value)->setTimezone('UTC')->format('Y-m-d H:i:s');
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
}
