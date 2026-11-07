<?php

namespace App\Services\DataServices\Poe;

use App\DataTransferObjects\RepositoryObjects\League\MainPoeLeagueDto;
use App\Enums\CacheTtlEnum;
use App\Enums\Tags\BucketMetricsTagEnum;
use App\Enums\Tags\ExchangePriceTagEnum;
use App\Enums\Tags\PoeLeagueTagEnum;
use App\Repositories\Poe\PoeLeagueRepositoryContract;
use Illuminate\Support\Facades\Cache;

final readonly class PoeLeagueService implements PoeLeagueServiceContract
{
    public function __construct(
        private PoeLeagueRepositoryContract $repository,
    ) {}

    /**
     * @return MainPoeLeagueDto[]
     */
    #[\Override]
    public function mainPageLeagues(): array
    {
        return Cache::tags([
            PoeLeagueTagEnum::league->name,
            PoeLeagueTagEnum::league_get->name,
            ExchangePriceTagEnum::price_get->name,
            BucketMetricsTagEnum::bucket_metrics_get->name,
        ])->remember(
            'mainPageLeagues',
            CacheTtlEnum::HOUR->jiggle(),
            fn () => $this->repository->allMainLeaguesWithSparkline(
                config('poe.base.default_main_trade_id'),
                now()->subDays(config('poe.base.sparkline_days')),
            )
        );
    }
}
