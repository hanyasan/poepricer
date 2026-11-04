<?php

namespace Tests\Unit;

use App\DataTransferObjects\ParsingObjects\Exchange\PoeNinjaExchangeItem;
use App\DataTransferObjects\ParsingObjects\Exchange\PoeNinjaExchangeLine;
use App\DataTransferObjects\ParsingObjects\Exchange\PoeNinjaExchangeResponse;
use App\DataTransferObjects\ParsingObjects\PoeNinjaLeague;
use App\Exceptions\Mapping\MapInvalidSourceException;
use App\Exceptions\Mapping\MappingErrorException;
use App\Utility\Mapper\MapperService;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MapperServiceTest extends TestCase
{
    private MapperService $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new MapperService;
    }

    public function test_it_maps_a_nested_json_object_into_the_given_dto(): void
    {
        $json = json_encode([
            'core' => [
                'items' => [
                    ['id' => 'item-a', 'name' => 'Item A', 'category' => 'type-alpha'],
                ],
                'rates' => ['item-a' => 1.0, 'item-b' => 0.0089],
            ],
            'lines' => [
                ['id' => 'line-1', 'primaryValue' => 12.5],
                ['id' => 'line-2'],
            ],
            'items' => [
                ['id' => 'item-b', 'name' => 'Item B', 'category' => 'type-alpha'],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = $this->mapper->mapFromJson(PoeNinjaExchangeResponse::class, $json);

        $this->assertInstanceOf(PoeNinjaExchangeResponse::class, $result);
        $this->assertSame(['item-a' => 1.0, 'item-b' => 0.0089], $result->core->rates);
        $this->assertInstanceOf(PoeNinjaExchangeItem::class, $result->core->items[0]);
        $this->assertSame('Item A', $result->core->items[0]->name);

        $this->assertCount(2, $result->lines);
        $this->assertInstanceOf(PoeNinjaExchangeLine::class, $result->lines[0]);
        $this->assertSame(12.5, $result->lines[0]->primaryValue);
        $this->assertNull($result->lines[1]->primaryValue);
    }

    public function test_it_ignores_superfluous_keys_in_the_source(): void
    {
        $json = json_encode([
            'name' => 'League One',
            'id' => 'league-a',
            'unknown_field' => ['deeply' => 'nested'],
        ], JSON_THROW_ON_ERROR);

        $result = $this->mapper->mapFromJson(PoeNinjaLeague::class, $json);

        $this->assertInstanceOf(PoeNinjaLeague::class, $result);
        $this->assertSame('League One', $result->name);
        $this->assertSame('league-a', $result->id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_it_throws_a_mapping_error_when_the_json_does_not_match_the_dto(array $payload): void
    {
        $this->expectException(MappingErrorException::class);

        $this->mapper->mapFromJson(PoeNinjaLeague::class, json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'missing required key' => [['name' => 'League One']],
            'value of the wrong type' => [['name' => 'League One', 'id' => 42]],
            'not an object at all' => [['League One', 'league-a']],
        ];
    }

    #[DataProvider('invalidJsonSources')]
    public function test_it_throws_an_invalid_source_error_when_the_json_cannot_be_read(string $invalidJson): void
    {
        $this->expectException(MapInvalidSourceException::class);

        $this->mapper->mapFromJson(PoeNinjaLeague::class, $invalidJson);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidJsonSources(): array
    {
        return [
            'malformed json' => ['{"name": "League One", '],
            'empty string' => [''],
            'null document' => ['null'],
        ];
    }

    public function test_it_maps_a_json_list_into_an_array_of_dtos(): void
    {
        $json = json_encode([
            ['name' => 'League One', 'id' => 'league-a'],
            ['name' => 'League Two', 'id' => 'league-two'],
        ], JSON_THROW_ON_ERROR);

        $result = $this->mapper->mapArrayFromJson(PoeNinjaLeague::class, $json);

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(PoeNinjaLeague::class, $result);
        $this->assertSame('league-a', $result[0]->id);
        $this->assertSame('league-two', $result[1]->id);
    }

    public function test_it_returns_an_empty_array_for_an_empty_json_list(): void
    {
        $this->assertSame([], $this->mapper->mapArrayFromJson(PoeNinjaLeague::class, '[]'));
    }

    public function test_it_throws_a_mapping_error_when_one_element_of_the_list_is_invalid(): void
    {
        $json = json_encode([
            ['name' => 'League One', 'id' => 'league-a'],
            ['name' => 'League Two'],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(MappingErrorException::class);

        $this->mapper->mapArrayFromJson(PoeNinjaLeague::class, $json);
    }

    public function test_it_throws_an_invalid_source_error_when_the_list_json_cannot_be_read(): void
    {
        $this->expectException(MapInvalidSourceException::class);

        $this->mapper->mapArrayFromJson(PoeNinjaLeague::class, 'not json at all');
    }

    public function test_it_maps_an_eloquent_collection_into_an_array_of_dtos(): void
    {
        $collection = new Collection([
            ['name' => 'League One', 'id' => 'league-a', 'extra' => true],
            ['name' => 'League Two', 'id' => 'league-two', 'extra' => false],
        ]);

        $result = $this->mapper->mapEloquentCollection(PoeNinjaLeague::class, $collection);

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(PoeNinjaLeague::class, $result);
        $this->assertSame('League One', $result[0]->name);
        $this->assertSame('league-two', $result[1]->id);
    }

    public function test_it_returns_an_empty_array_for_an_empty_eloquent_collection(): void
    {
        $this->assertSame(
            [],
            $this->mapper->mapEloquentCollection(PoeNinjaLeague::class, new Collection),
        );
    }

    public function test_it_throws_a_mapping_error_when_an_eloquent_row_is_invalid(): void
    {
        $collection = new Collection([
            ['name' => 'League One', 'id' => 'league-a'],
            ['id' => 'league-two'],
        ]);

        $this->expectException(MappingErrorException::class);

        $this->mapper->mapEloquentCollection(PoeNinjaLeague::class, $collection);
    }
}
