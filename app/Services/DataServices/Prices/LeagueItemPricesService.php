<?php

namespace App\Services\DataServices\Prices;

use App\DataTransferObjects\RepositoryObjects\Exchange\ExchangeItemDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheck\ItemPriceCurrencyRowDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheck\ItemPriceSnapshotRowDto;
use App\DataTransferObjects\ServiceObjects\ItemPrices\ItemPriceChangeDto;
use App\DataTransferObjects\ServiceObjects\ItemPrices\ItemPricesDto;
use App\DataTransferObjects\ServiceObjects\ItemPrices\ItemPricesQueryDto;
use App\DataTransferObjects\ServiceObjects\ItemPrices\ItemPriceStatsDto;
use App\Enums\CacheTtlEnum;
use App\Enums\ItemPriceWindowEnum;
use App\Enums\Tags\ExchangePriceTagEnum;
use App\Exceptions\Item\ExchangeItemNotFoundException;
use App\Repositories\Poe\Exchange\ExchangeItem\ExchangeItemRepositoryContract;
use App\Repositories\Poe\Exchange\PriceCheck\ExchangeItemPriceHistoryRepositoryContract;
use App\Repositories\Poe\Exchange\PriceCheck\PriceCheckRepositoryContract;
use App\Services\DataServices\Metrics\LeagueMetricsLookupServiceContract;
use App\Utility\Statistics\RobustStatistics;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final readonly class LeagueItemPricesService implements LeagueItemPricesServiceContract
{
    /**
     * Spans of the price change shown on the page, in hours.
     *
     * @var int[]
     */
    private const array CHANGE_HOURS = [1, 6, 24, 48];

    public function __construct(
        private LeagueMetricsLookupServiceContract $lookupService,
        private ExchangeItemRepositoryContract $itemRepository,
        private PriceCheckRepositoryContract $priceCheckRepository,
        private ExchangeItemPriceHistoryRepositoryContract $historyRepository,
        private RobustStatistics $statistics,
    ) {}

    #[\Override]
    public function prices(string $leagueSlug, string $itemSlug, ItemPricesQueryDto $query): ItemPricesDto
    {
        $league = $this->lookupService->findLeague($leagueSlug);
        $item = $this->itemRepository->findByTradeId($league->id, $itemSlug) ?? throw new ExchangeItemNotFoundException;

        $tags = [
            ExchangePriceTagEnum::price_get->name,
            ExchangePriceTagEnum::price_league_id->name.$league->id,
        ];
        $prefix = 'leagueItemPrices:'.$league->id.':'.$item->id.':'.$query->window->value;

        /**
         * The currencies are cached apart from the series, so the series is cached by the resolved
         * currency only and an arbitrary requested one cannot fill the cache.
         *
         * @var array{from: string|null, to: string|null, currencies: ItemPriceCurrencyRowDto[]} $scope
         */
        $scope = Cache::tags($tags)->remember(
            $prefix.':scope',
            CacheTtlEnum::HOUR->jiggle(),
            fn (): array => $this->scope($league->id, $item, $query->window),
        );

        $currency = $this->resolveCurrency($scope['currencies'], $query->currency);

        if ($currency === null || $scope['from'] === null || $scope['to'] === null) {
            return new ItemPricesDto(null, $query->window, $scope['from'], $scope['to'], $scope['currencies']);
        }

        return Cache::tags($tags)->remember(
            $prefix.':'.$currency,
            CacheTtlEnum::HOUR->jiggle(),
            fn (): ItemPricesDto => $this->build($league->id, $item->id, $currency, $query->window, $scope),
        );
    }

    /**
     * @return array{from: string|null, to: string|null, currencies: ItemPriceCurrencyRowDto[]}
     */
    private function scope(int $leagueId, ExchangeItemDto $item, ItemPriceWindowEnum $window): array
    {
        $last = $this->priceCheckRepository->findLatestCreatedAtByLeague($leagueId);

        if ($last === null) {
            return ['from' => null, 'to' => null, 'currencies' => []];
        }

        $to = CarbonImmutable::parse($last)->utc();
        $from = $to->subHours($window->hours());

        return [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'currencies' => $this->historyRepository->getCurrencies($leagueId, $item->id, $item->trade_id, $from, $to),
        ];
    }

    /**
     * @param  array{from: string|null, to: string|null, currencies: ItemPriceCurrencyRowDto[]}  $scope
     */
    private function build(int $leagueId, int $itemId, string $currency, ItemPriceWindowEnum $window, array $scope): ItemPricesDto
    {
        $points = $this->historyRepository->getPoints(
            $leagueId,
            $itemId,
            $currency,
            CarbonImmutable::parse((string) $scope['from']),
            CarbonImmutable::parse((string) $scope['to']),
        );

        return new ItemPricesDto(
            currency: $currency,
            window: $window,
            from: $scope['from'],
            to: $scope['to'],
            currencies: $scope['currencies'],
            points: $points,
            stats: $this->stats($points, $window),
        );
    }

    /**
     * The requested currency when the item was priced in it within the window, otherwise the most
     * covered one, null when there is none.
     *
     * @param  ItemPriceCurrencyRowDto[]  $currencies
     */
    private function resolveCurrency(array $currencies, ?string $requested): ?string
    {
        foreach ($currencies as $currency) {
            if ($currency->slug === $requested) {
                return $currency->slug;
            }
        }

        return $currencies[0]->slug ?? null;
    }

    /**
     * @param  ItemPriceSnapshotRowDto[]  $points  oldest first
     */
    private function stats(array $points, ItemPriceWindowEnum $window): ?ItemPriceStatsDto
    {
        if ($points === []) {
            return null;
        }

        $current = $points[array_key_last($points)];
        $prices = array_map(static fn (ItemPriceSnapshotRowDto $point): float => $point->price, $points);
        $units = array_map(static fn (ItemPriceSnapshotRowDto $point): float => $point->units, $points);

        return new ItemPriceStatsDto(
            current: $current,
            min: min($prices),
            max: max($prices),
            median: $this->statistics->median($prices),
            averageUnits: array_sum($units) / (float) count($units),
            changes: $this->changes($points, $current, $window),
        );
    }

    /**
     * Change of the price over each span that fits the window. The reference is the last point
     * not later than the span before the current one, null when the series does not reach back.
     *
     * @param  ItemPriceSnapshotRowDto[]  $points  oldest first
     * @return ItemPriceChangeDto[]
     */
    private function changes(array $points, ItemPriceSnapshotRowDto $current, ItemPriceWindowEnum $window): array
    {
        $currentAt = CarbonImmutable::parse($current->at);
        $changes = [];

        foreach (self::CHANGE_HOURS as $hours) {
            if ($hours > $window->hours()) {
                continue;
            }

            $threshold = $currentAt->subHours($hours);
            $reference = null;

            foreach ($points as $point) {
                if (CarbonImmutable::parse($point->at)->greaterThan($threshold)) {
                    break;
                }

                $reference = $point;
            }

            $changes[] = new ItemPriceChangeDto(
                $hours,
                $reference === null ? null : $current->price / $reference->price - 1.0,
            );
        }

        return $changes;
    }
}
