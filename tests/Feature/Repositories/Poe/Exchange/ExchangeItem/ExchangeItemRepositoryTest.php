<?php

namespace Tests\Feature\Repositories\Poe\Exchange\PriceCheck;

use App\DataTransferObjects\RepositoryObjects\Exchange\ExchangeItemDto;
use App\Models\Poe\Exchange\ExchangeItem;
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

        $itemA = $this->item($league, 'item-a', 'Item A');
        $this->item($league, 'item-b', 'Item B');
        $this->item($otherLeague, 'item-c', 'Item C');

        $result = $this->repository()->getAllByLeague($league->id);

        $this->assertContainsOnlyInstancesOf(ExchangeItemDto::class, $result);
        $this->assertCount(2, $result);
        $this->assertContains($itemA->id, array_column($result, 'id'));
        $this->assertNotContains(
            ExchangeItem::query()->where('trade_id', 'item-c')->value('id'),
            array_column($result, 'id'),
        );
    }

    public function test_maps_the_item_fields(): void
    {
        $league = $this->league('Mapped Items League');
        $itemB = $this->item($league, 'item-b', 'Item B', 'type-alpha');

        $dto = $this->findByTradeId($this->repository()->getAllByLeague($league->id), 'item-b');

        $this->assertSame($itemB->id, $dto->id);
        $this->assertSame('Item B', $dto->name);
        $this->assertSame('item-b', $dto->trade_id);
        $this->assertSame($league->id, $dto->poe_league_id);
        $this->assertSame('type-alpha', $dto->poe_category);
    }

    public function test_maps_a_missing_category_as_null(): void
    {
        $league = $this->league('Uncategorised Items League');
        $this->item($league, 'item-a', 'Item A');

        $dto = $this->findByTradeId($this->repository()->getAllByLeague($league->id), 'item-a');

        $this->assertNull($dto->poe_category);
    }

    public function test_returns_empty_array_when_the_league_has_no_items(): void
    {
        $league = $this->league('Empty Items League');

        $this->assertSame([], $this->repository()->getAllByLeague($league->id));
    }

    public function test_upsert_inserts_items(): void
    {
        $league = $this->league('Upsert Items League');

        $this->repository()->upsert([
            ['name' => 'Item A', 'trade_id' => 'item-a', 'poe_league_id' => $league->id, 'poe_category' => 'type-alpha'],
            ['name' => 'Item B', 'trade_id' => 'item-b', 'poe_league_id' => $league->id, 'poe_category' => 'type-alpha'],
        ]);

        $this->assertDatabaseHas('exchange_items', [
            'name' => 'Item A',
            'trade_id' => 'item-a',
            'poe_league_id' => $league->id,
            'poe_category' => 'type-alpha',
        ]);
        $this->assertDatabaseHas('exchange_items', [
            'name' => 'Item B',
            'trade_id' => 'item-b',
            'poe_league_id' => $league->id,
            'poe_category' => 'type-alpha',
        ]);
    }

    public function test_upsert_updates_name_and_category_of_an_existing_item(): void
    {
        $league = $this->league('Upsert Rename League');
        $this->item($league, 'item-b', 'Item B', 'Old Category');

        $this->repository()->upsert([
            [
                'name' => 'Item B Renamed',
                'trade_id' => 'item-b',
                'poe_league_id' => $league->id,
                'poe_category' => 'New Category',
            ],
        ]);

        $this->assertSame(
            1,
            ExchangeItem::query()
                ->where('trade_id', 'item-b')
                ->where('poe_league_id', $league->id)
                ->count(),
        );
        $this->assertDatabaseHas('exchange_items', [
            'trade_id' => 'item-b',
            'poe_league_id' => $league->id,
            'name' => 'Item B Renamed',
            'poe_category' => 'New Category',
        ]);
    }

    public function test_upsert_keeps_the_same_trade_id_separate_for_each_league(): void
    {
        $league = $this->league('Upsert League One');
        $otherLeague = $this->league('Upsert League Two');

        $this->repository()->upsert([
            ['name' => 'Item A', 'trade_id' => 'item-a', 'poe_league_id' => $league->id, 'poe_category' => 'type-alpha'],
            ['name' => 'Item A', 'trade_id' => 'item-a', 'poe_league_id' => $otherLeague->id, 'poe_category' => 'type-alpha'],
        ]);

        $this->assertDatabaseHas('exchange_items', ['trade_id' => 'item-a', 'poe_league_id' => $league->id]);
        $this->assertDatabaseHas('exchange_items', ['trade_id' => 'item-a', 'poe_league_id' => $otherLeague->id]);
    }

    public function test_upsert_with_no_rows_persists_nothing(): void
    {
        $before = ExchangeItem::query()->count();

        $this->repository()->upsert([]);

        $this->assertSame($before, ExchangeItem::query()->count());
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
            'slug' => 'poe2-ninja-'.strtolower(str_replace(' ', '-', $name)),
            'is_active' => true,
            'poe_version_id' => (int) PoeVersion::query()->where('version', 'poe2')->value('id'),
        ]);
    }

    private function item(PoeLeague $league, string $tradeId, string $name, ?string $category = null): ExchangeItem
    {
        return ExchangeItem::query()->create([
            'name' => $name,
            'trade_id' => $tradeId,
            'poe_category' => $category,
            'poe_league_id' => $league->id,
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
}
