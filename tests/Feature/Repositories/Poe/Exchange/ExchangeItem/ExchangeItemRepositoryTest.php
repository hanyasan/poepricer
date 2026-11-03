<?php

namespace Tests\Feature\Repositories\Poe\Exchange\ExchangeItem;

use App\DataTransferObjects\RepositoryObjects\Exchange\ExchangeItemDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\ExchangeItemPriceDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\ExchangeItemWithPricesDto;
use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\Exchange\ExchangeItemPrice;
use App\Models\Poe\Exchange\PriceCheckCore;
use App\Models\Poe\PoeLeague;
use App\Models\Poe\PoeVersion;
use App\Repositories\Poe\Exchange\ExchangeItem\ExchangeItemRepositoryContract;
use Tests\FeatureTestCase;

class ExchangeItemRepositoryTest extends FeatureTestCase
{
    public function test_returns_only_items_of_the_requested_league(): void
    {
        $league = $this->league('Items League');
        $otherLeague = $this->league('Items Other League');

        $chaos = $this->item($league, 'chaos', 'Chaos Orb');
        $this->item($league, 'divine', 'Divine Orb');
        $this->item($otherLeague, 'exalted', 'Exalted Orb');

        $result = $this->repository()->getAllByLeague($league->id);

        $this->assertContainsOnlyInstancesOf(ExchangeItemDto::class, $result);
        $this->assertEqualsCanonicalizing(
            [$chaos->id, ExchangeItem::query()->where('trade_id', 'divine')->value('id')],
            array_column($result, 'id'),
        );

        $chaosDto = $this->findByTradeId($result, 'chaos');

        $this->assertSame('Chaos Orb', $chaosDto->name);
        $this->assertSame('chaos', $chaosDto->trade_id);
        $this->assertSame($league->id, $chaosDto->poe_league_id);
    }

    public function test_returns_empty_array_when_the_league_has_no_items(): void
    {
        $league = $this->league('Empty Items League');

        $this->assertSame([], $this->repository()->getAllByLeague($league->id));
    }

    public function test_returns_types_of_the_league_whatever_prices_they_have(): void
    {
        $league = $this->league('Priced League');

        $divine = $this->item($league, 'divine', 'Divine Orb');
        $chaos = $this->item($league, 'chaos', 'Chaos Orb');

        $core = $this->core($league);
        $this->price($divine, $core, 210.0);

        $result = $this->repository()->getAllByLeagueWithPriceByCore($league->id, $core->id);

        $this->assertContainsOnlyInstancesOf(ExchangeItemWithPricesDto::class, $result);
        $this->assertCount(2, $result);

        $divineDto = $this->findByTradeIdWithPrices($result, 'divine');
        $chaosDto = $this->findByTradeIdWithPrices($result, 'chaos');

        $this->assertCount(1, $divineDto->prices);
        $this->assertSame([], $chaosDto->prices);
    }

    public function test_returns_only_prices_of_the_requested_core(): void
    {
        $league = $this->league('Two Cores League');

        $divine = $this->item($league, 'divine', 'Divine Orb');

        $older = $this->core($league, '2030-01-01 10:00:00');
        $newer = $this->core($league, '2030-01-01 12:00:00');

        $this->price($divine, $older, 200.0);
        $this->price($divine, $newer, 250.0);

        $olderResult = $this->repository()->getAllByLeagueWithPriceByCore($league->id, $older->id);
        $newerResult = $this->repository()->getAllByLeagueWithPriceByCore($league->id, $newer->id);

        $this->assertSame([200.0], array_column($this->findByTradeIdWithPrices($olderResult, 'divine')->prices, 'rate'));
        $this->assertSame([250.0], array_column($this->findByTradeIdWithPrices($newerResult, 'divine')->prices, 'rate'));
    }

    public function test_maps_prices_to_dtos(): void
    {
        $league = $this->league('Mapped Prices League');

        $divine = $this->item($league, 'divine', 'Divine Orb');
        $core = $this->core($league);
        $this->price($divine, $core, 2.5);

        $result = $this->repository()->getAllByLeagueWithPriceByCore($league->id, $core->id);

        $prices = $this->findByTradeIdWithPrices($result, 'divine')->prices;

        $this->assertCount(1, $prices);
        $this->assertInstanceOf(ExchangeItemPriceDto::class, $prices[0]);
        $this->assertSame(2.5, $prices[0]->rate);
        $this->assertSame(
            ExchangeItemPrice::query()->where('exchange_item_id', $divine->id)->value('id'),
            $prices[0]->id,
        );
    }

    public function test_returns_empty_array_when_the_league_has_no_items_for_the_core(): void
    {
        $league = $this->league('No Items For Core League');

        $this->assertSame(
            [],
            $this->repository()->getAllByLeagueWithPriceByCore($league->id, $this->core($league)->id),
        );
    }

    public function test_upsert_inserts_items(): void
    {
        $league = $this->league('Upsert Items League');

        $this->repository()->upsert([
            ['name' => 'Chaos Orb', 'trade_id' => 'chaos', 'poe_league_id' => $league->id],
            ['name' => 'Divine Orb', 'trade_id' => 'divine', 'poe_league_id' => $league->id],
        ]);

        $this->assertDatabaseHas('exchange_items', [
            'name' => 'Chaos Orb',
            'trade_id' => 'chaos',
            'poe_league_id' => $league->id,
        ]);
        $this->assertDatabaseHas('exchange_items', [
            'name' => 'Divine Orb',
            'trade_id' => 'divine',
            'poe_league_id' => $league->id,
        ]);
    }

    public function test_upsert_updates_the_name_of_an_existing_item(): void
    {
        $league = $this->league('Upsert Rename League');
        $this->item($league, 'divine', 'Divine Orb');

        $this->repository()->upsert([
            ['name' => 'Divine Orb Renamed', 'trade_id' => 'divine', 'poe_league_id' => $league->id],
        ]);

        $this->assertSame(
            1,
            ExchangeItem::query()
                ->where('trade_id', 'divine')
                ->where('poe_league_id', $league->id)
                ->count(),
        );
        $this->assertDatabaseHas('exchange_items', [
            'trade_id' => 'divine',
            'poe_league_id' => $league->id,
            'name' => 'Divine Orb Renamed',
        ]);
    }

    public function test_upsert_keeps_the_same_trade_id_separate_for_each_league(): void
    {
        $league = $this->league('Upsert League One');
        $otherLeague = $this->league('Upsert League Two');

        $this->repository()->upsert([
            ['name' => 'Chaos Orb', 'trade_id' => 'chaos', 'poe_league_id' => $league->id],
            ['name' => 'Chaos Orb', 'trade_id' => 'chaos', 'poe_league_id' => $otherLeague->id],
        ]);

        $this->assertDatabaseHas('exchange_items', ['trade_id' => 'chaos', 'poe_league_id' => $league->id]);
        $this->assertDatabaseHas('exchange_items', ['trade_id' => 'chaos', 'poe_league_id' => $otherLeague->id]);
    }

    private function repository(): ExchangeItemRepositoryContract
    {
        return $this->app->make(ExchangeItemRepositoryContract::class);
    }

    private function league(string $name): PoeLeague
    {
        return PoeLeague::query()->create([
            'league_name' => $name,
            'ninja_league_id' => 'ninja-'.strtolower(str_replace(' ', '-', $name)),
            'is_active' => true,
            'poe_version_id' => $this->poeVersionId('poe2'),
        ]);
    }

    private function poeVersionId(string $version): int
    {
        return (int) PoeVersion::query()->where('version', $version)->value('id');
    }

    private function item(PoeLeague $league, string $tradeId, string $name): ExchangeItem
    {
        return ExchangeItem::query()->create([
            'name' => $name,
            'trade_id' => $tradeId,
            'poe_league_id' => $league->id,
        ]);
    }

    private function core(PoeLeague $league, string $createdAt = '2030-01-01 10:00:00'): PriceCheckCore
    {
        return PriceCheckCore::query()->create([
            'poe_league_id' => $league->id,
            'created_at' => $createdAt,
        ]);
    }

    private function price(ExchangeItem $item, PriceCheckCore $core, float $rate): void
    {
        ExchangeItemPrice::query()->create([
            'rate' => $rate,
            'exchange_item_id' => $item->id,
            'price_check_core_id' => $core->id,
        ]);
    }

    /**
     * @param  ExchangeItemDto[]  $items
     */
    private function findByTradeId(array $items, string $tradeId): ExchangeItemDto
    {
        foreach ($items as $item) {
            if ($item->trade_id === $tradeId) {
                return $item;
            }
        }

        $this->fail("No exchange item dto with trade id [{$tradeId}] was returned.");
    }

    /**
     * @param  ExchangeItemWithPricesDto[]  $items
     */
    private function findByTradeIdWithPrices(array $items, string $tradeId): ExchangeItemWithPricesDto
    {
        foreach ($items as $item) {
            if ($item->trade_id === $tradeId) {
                return $item;
            }
        }

        $this->fail("No exchange item with prices dto with trade id [{$tradeId}] was returned.");
    }
}
