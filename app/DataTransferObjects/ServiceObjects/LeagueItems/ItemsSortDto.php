<?php

namespace App\DataTransferObjects\ServiceObjects\LeagueItems;

use App\Enums\ItemsSortFieldEnum;
use App\Enums\SortDirectionEnum;

final readonly class ItemsSortDto
{
    public function __construct(
        public ItemsSortFieldEnum $field = ItemsSortFieldEnum::Units,
        public SortDirectionEnum $direction = SortDirectionEnum::Desc,
    ) {}

    /**
     * Parses a `sort` query value, a leading minus means descending.
     */
    public static function fromQueryValue(string $value): ?self
    {
        $descending = str_starts_with($value, '-');
        $field = ItemsSortFieldEnum::tryFrom($descending ? substr($value, 1) : $value);

        if ($field === null) {
            return null;
        }

        return new self($field, $descending ? SortDirectionEnum::Desc : SortDirectionEnum::Asc);
    }
}
