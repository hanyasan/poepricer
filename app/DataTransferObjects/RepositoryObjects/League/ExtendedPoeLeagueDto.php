<?php

namespace App\DataTransferObjects\RepositoryObjects\League;

use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheckDto;
use App\DataTransferObjects\RepositoryObjects\PoeVersionDto;

final readonly class ExtendedPoeLeagueDto
{
    /**
     * @param  PriceCheckDto[]|null  $latest_price_check
     * @param  PriceCheckDto[]|null  $price_checks
     */
    public function __construct(
        public int $id,
        public string $league_name,
        public string $ninja_league_id,
        public bool $is_active,
        public int $poe_version_id,
        public PoeVersionDto $poe_version,
        public ?LeagueCheckRuleDto $check_rule = null,
        public ?array $latest_price_check = null,
        public ?array $price_checks = null,
    ) {}
}
