<?php

namespace App\Services\NinjaImport;

use App\DataTransferObjects\ParsingObjects\Exchange\PoeNinjaExchangeCore;
use App\DataTransferObjects\ParsingObjects\Exchange\PoeNinjaExchangeItem;
use App\DataTransferObjects\ParsingObjects\Exchange\PoeNinjaExchangeLine;
use App\DataTransferObjects\ParsingObjects\PoeNinjaLeague;
use App\DataTransferObjects\RepositoryObjects\Exchange\ExchangeItemDto;
use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheckDto;
use App\DataTransferObjects\RepositoryObjects\ExchangeTypeDto;
use App\DataTransferObjects\RepositoryObjects\League\ExtendedPoeLeagueDto;
use App\DataTransferObjects\RepositoryObjects\League\PoeLeagueDto;
use App\DataTransferObjects\RepositoryObjects\PoeVersionDto;
use App\Enums\Tags\PoeLeagueTagEnum;
use App\Exceptions\Mapping\MapInvalidSourceException;
use App\Exceptions\Mapping\MappingErrorException;
use App\HttpClients\NinjaClient;
use App\Repositories\Poe\Exchange\ExchangeItem\ExchangeItemRepositoryContract;
use App\Repositories\Poe\Exchange\PriceCheck\PriceCheckRepositoryContract;
use App\Repositories\Poe\PoeLeagueRepositoryContract;
use App\Services\DataServices\Metrics\PriceCheckCurrencyMixServiceContract;
use App\Services\DataServices\Poe\ExchangeTypeServiceContract;
use App\Services\DataServices\Poe\PoeVersionServiceContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class NinjaImportService implements NinjaImportServiceContract
{
    public function __construct(
        private PoeLeagueRepositoryContract $poeLeagueRepository,
        private PoeVersionServiceContract $poeVersionService,
        private NinjaClient $ninjaClient,
        private PriceCheckRepositoryContract $priceCheckRepository,
        private ExchangeTypeServiceContract $exchangeTypeService,
        private ExchangeItemRepositoryContract $exchangeItemRepository,
        private PriceCheckCurrencyMixServiceContract $currencyMixService,
    ) {}

    #[\Override]
    public function checkExchangePrices(
        ExtendedPoeLeagueDto $league,
    ): void {
        $exchangeTypes = $this->exchangeTypeService->getByVersion($league->poe_version->id);

        $imported = DB::transaction(function () use ($league, $exchangeTypes) {
            $priceCheck = $this->priceCheckRepository->createPriceCheck($league->id);
            $exchangeTypesImported = 0;

            foreach ($exchangeTypes as $exchangeType) {
                try {
                    $this->importLeagueExchangePrices(
                        $league,
                        $priceCheck,
                        $exchangeType,
                    );

                    $exchangeTypesImported += 1;
                } catch (\Throwable $exception) {
                    Log::error(
                        'Failed for import type:'.
                        $exchangeType->category.
                        ' of league:'.
                        $league->league_name.
                        ' with message: '.
                        $exception->getMessage()
                    );

                    throw $exception;
                }
            }

            $this->currencyMixService->mixInCurrencies($priceCheck->id, $league->id);

            return $exchangeTypesImported;
        });

        Log::info(
            'Prices for league '.
            $league->league_name.
            ' imported, for '.
            $imported.
            ' types'
        );
    }

    #[\Override]
    public function updateAllLeaguesFromNinja(): void
    {
        $versions = $this->poeVersionService->getAll();

        foreach ($versions as $version) {
            try {
                $this->updateLeaguesFromNinjaByVersion($version);
            } catch (\Throwable $exception) {
                Log::error('Unable to update leagues for version '.
                    $version->version.
                    ' - message: '.
                    $exception->getMessage()
                );
            }
        }
    }

    /**
     * @throws MappingErrorException
     * @throws ConnectionException
     * @throws RequestException
     * @throws MapInvalidSourceException
     */
    public function updateLeaguesFromNinjaByVersion(PoeVersionDto $version): void
    {
        $ninjaLeagues = collect($this->ninjaClient->getEconomyLeagues($version->version));
        $currentLeagues = collect($this->poeLeagueRepository->getAllActive($version->id));

        DB::transaction(function () use ($ninjaLeagues, $currentLeagues, $version) {
            $this->poeLeagueRepository->upsert($this->constructUpsertData(
                $ninjaLeagues,
                $currentLeagues,
                $version,
            ));

            $this->poeLeagueRepository->upsertDefaultRulesForLeagues(
                collect($this->poeLeagueRepository->getAllActiveWithNoRule($version->id))->pluck('id')->toArray(),
                config('poe.price_check.default_hours_check_rule') * 60,
            );
        });

        Cache::tags([PoeLeagueTagEnum::league_version->name.$version->id])->flush();
    }

    /**
     * @return array<int, array{
     *      rate: float,
     *      exchange_item_trade_id: string,
     *      name: string,
     * }>
     */
    private function prepCoreTypes(PoeNinjaExchangeCore $core): array
    {
        return array_map(static function (PoeNinjaExchangeItem $item) use ($core) {
            return [
                'rate' => $core->rates[$item->id] ?? 1,
                'exchange_item_trade_id' => $item->id,
                'name' => $item->name,
            ];
        }, $core->items);
    }

    private function constructUpsertData(
        Collection $ninjaLeagues,
        Collection $currentLeagues,
        PoeVersionDto $poeVersion,
    ): array {
        $leaguesToInsert = $ninjaLeagues->map(static function (PoeNinjaLeague $league) use ($poeVersion) {
            return [
                'league_name' => $league->name,
                'ninja_league_id' => $league->id,
                'poe_version_id' => $poeVersion->id,
                'is_active' => true,
            ];
        });

        $ninjaIds = $leaguesToInsert->pluck('ninja_league_id');

        return $currentLeagues
            ->reject(static fn (PoeLeagueDto $league) => $ninjaIds->contains($league->ninja_league_id))
            ->map(static fn (PoeLeagueDto $league) => [
                'league_name' => $league->league_name,
                'ninja_league_id' => $league->ninja_league_id,
                'poe_version_id' => $league->poe_version_id,
                'is_active' => false,
            ])
            ->merge($leaguesToInsert)
            ->toArray();
    }

    private function importLeagueExchangePrices(
        ExtendedPoeLeagueDto $league,
        PriceCheckDto $priceCheck,
        ExchangeTypeDto $exchangeType,
    ): void {
        $exchangeResponse = $this->ninjaClient->getExchangeTypePrices(
            $league->poe_version->version,
            $league->ninja_league_id,
            $exchangeType->value,
        );

        DB::transaction(function () use ($league, $priceCheck, $exchangeResponse, $exchangeType) {
            $coreDto = $this->priceCheckRepository->createCore(
                $priceCheck->id,
                $exchangeType->id,
                $this->prepCoreTypes($exchangeResponse->core),
            );

            $this->exchangeItemRepository->upsert(
                array_map(fn (PoeNinjaExchangeItem $item) => [
                    'name' => $item->name,
                    'trade_id' => $item->id,
                    'poe_category' => $item->category,
                    'poe_league_id' => $league->id,
                ], $exchangeResponse->items)
            );

            $this->priceCheckRepository->insertPrices($this->prepPrices(
                $this->exchangeItemRepository->getAllByLeague($league->id),
                $coreDto->id,
                $exchangeResponse->lines,
            ));
        });
    }

    /**
     * @param  array<int, ExchangeItemDto>  $exchangeItems
     * @param  array<int, PoeNinjaExchangeLine>  $items
     * @return array<int, array{
     *     rate: float,
     *     volume: float|null,
     *     price_check_core_id: int,
     *     exchange_item_id: int,
     * }>
     */
    private function prepPrices(
        array $exchangeItems,
        int $coreId,
        array $items,
    ): array {
        $items = collect($items);
        $prices = [];

        foreach ($exchangeItems as $exchangeItem) {
            /** @var PoeNinjaExchangeLine|null $tradeItem */
            $tradeItem = $items->firstWhere('id', $exchangeItem->trade_id);

            if (! $tradeItem || is_null($tradeItem->primaryValue)) {
                continue;
            }

            $prices[] = [
                'rate' => $tradeItem->primaryValue,
                'volume' => $tradeItem->volumePrimaryValue,
                'price_check_core_id' => $coreId,
                'exchange_item_id' => $exchangeItem->id,
            ];
        }

        return $prices;
    }
}
