<?php

namespace App\Services\DataServices\Metrics;

use App\DataTransferObjects\RepositoryObjects\Exchange\Metrics\ExchangeMetricsItemDto;
use App\DataTransferObjects\RepositoryObjects\League\PoeLeagueDto;
use App\Enums\CacheTtlEnum;
use App\Enums\Tags\MetricsItemTagEnum;
use App\Enums\Tags\PoeLeagueTagEnum;
use App\Exceptions\League\LeagueNotFoundException;
use App\Repositories\Poe\Exchange\Metrics\ExchangeMetricsItemRepositoryContract;
use App\Repositories\Poe\PoeLeagueRepositoryContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

final readonly class LeagueMetricsLookupService implements LeagueMetricsLookupServiceContract
{
    public function __construct(
        private PoeLeagueRepositoryContract $leagueRepository,
        private ExchangeMetricsItemRepositoryContract $metricsItemRepository,
    ) {}

    #[\Override]
    public function findLeague(string $slug): PoeLeagueDto
    {
        return Cache::tags([
            PoeLeagueTagEnum::league->name,
            PoeLeagueTagEnum::league_get->name,
        ])->remember(
            'leagueBySlug:'.$slug,
            CacheTtlEnum::HOUR->jiggle(),
            fn (): PoeLeagueDto => $this->leagueRepository->findActiveBySlug($slug) ?? throw new LeagueNotFoundException,
        );
    }

    /**
     * @return ExchangeMetricsItemDto[]
     */
    #[\Override]
    public function metricsItems(int $leagueId): array
    {
        return Cache::tags([
            MetricsItemTagEnum::metrics_items_league_id->name.$leagueId,
        ])->remember(
            'leagueMetricsItems:'.$leagueId,
            CacheTtlEnum::HOUR->jiggle(),
            function () use ($leagueId): array {
                $defaultTradeId = (string) config('poe.base.default_main_trade_id');
                $metricsItems = $this->metricsItemRepository->getAllByLeague($leagueId);

                usort(
                    $metricsItems,
                    static fn (ExchangeMetricsItemDto $a, ExchangeMetricsItemDto $b): int => (int) ($b->exchange_item->trade_id === $defaultTradeId)
                        <=> (int) ($a->exchange_item->trade_id === $defaultTradeId),
                );

                return $metricsItems;
            },
        );
    }

    #[\Override]
    public function pickMetricsItem(int $leagueId, ?string $currency): ?ExchangeMetricsItemDto
    {
        $metricsItems = $this->metricsItems($leagueId);

        if ($currency === null) {
            return $metricsItems[0] ?? null;
        }

        foreach ($metricsItems as $metricsItem) {
            if ($metricsItem->exchange_item->trade_id === $currency) {
                return $metricsItem;
            }
        }

        throw ValidationException::withMessages([
            'currency' => 'The league has no such metric currency.',
        ]);
    }
}
