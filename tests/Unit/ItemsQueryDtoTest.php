<?php

namespace Tests\Unit;

use App\DataTransferObjects\ServiceObjects\LeagueItems\ItemsSortDto;
use App\DataTransferObjects\ServiceObjects\PaginationDto;
use App\Enums\ItemsSortFieldEnum;
use App\Enums\SortDirectionEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ItemsQueryDtoTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: ItemsSortFieldEnum, 2: SortDirectionEnum}>
     */
    public static function validSorts(): array
    {
        return [
            'ascending' => ['price', ItemsSortFieldEnum::Price, SortDirectionEnum::Asc],
            'descending' => ['-residual', ItemsSortFieldEnum::Residual, SortDirectionEnum::Desc],
            'camel case field' => ['-unusualRatio', ItemsSortFieldEnum::UnusualRatio, SortDirectionEnum::Desc],
        ];
    }

    #[DataProvider('validSorts')]
    public function test_a_sort_query_value_is_parsed_into_field_and_direction(
        string $value,
        ItemsSortFieldEnum $field,
        SortDirectionEnum $direction,
    ): void {
        $sort = ItemsSortDto::fromQueryValue($value);

        $this->assertNotNull($sort);
        $this->assertSame($field, $sort->field);
        $this->assertSame($direction, $sort->direction);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidSorts(): array
    {
        return [
            'unknown field' => ['bogus'],
            'unknown descending field' => ['-bogus'],
            'only a minus' => ['-'],
            'empty' => [''],
            'wrong case' => ['logreturn'],
        ];
    }

    #[DataProvider('invalidSorts')]
    public function test_an_unknown_sort_query_value_is_not_parsed(string $value): void
    {
        $this->assertNull(ItemsSortDto::fromQueryValue($value));
    }

    public function test_the_default_sort_is_units_descending(): void
    {
        $sort = new ItemsSortDto;

        $this->assertSame(ItemsSortFieldEnum::Units, $sort->field);
        $this->assertSame(SortDirectionEnum::Desc, $sort->direction);
    }

    public function test_the_pagination_offset_skips_the_previous_pages(): void
    {
        $this->assertSame(0, (new PaginationDto(1, 50))->offset());
        $this->assertSame(100, (new PaginationDto(3, 50))->offset());
    }
}
