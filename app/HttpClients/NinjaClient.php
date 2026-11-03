<?php

namespace App\HttpClients;

use App\DataTransferObjects\ParsingObjects\Exchange\PoeNinjaExchangeResponse;
use App\DataTransferObjects\ParsingObjects\PoeNinjaLeague;
use App\Exceptions\Mapping\MapInvalidSourceException;
use App\Exceptions\Mapping\MappingErrorException;
use App\Utility\Mapper\MapperServiceContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final readonly class NinjaClient
{
    public function __construct(
        private MapperServiceContract $mapper,
    ) {}

    /**
     * @throws ConnectionException
     * @throws RequestException
     * @throws MappingErrorException
     * @throws MapInvalidSourceException
     */
    public function getExchangeTypePrices(
        string $version,
        string $ninjaLeagueId,
        string $exchangeType,
    ): PoeNinjaExchangeResponse {
        /** @var Response $response */
        $response = $this->request()
            ->get($version.'/api/economy/exchange/current/overview', [
                'league' => $ninjaLeagueId,
                'type' => $exchangeType,
            ]);

        $response->throw();

        return $this->mapper->mapFromJson(
            PoeNinjaExchangeResponse::class,
            $response->body(),
        );
    }

    /**
     * @return PoeNinjaLeague[]
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws MappingErrorException
     * @throws MapInvalidSourceException
     */
    public function getEconomyLeagues(string $version): array
    {
        /** @var Response $response */
        $response = $this->request()
            ->get($version.'/api/economy/leagues');

        $response->throw();

        return $this->mapper->mapArrayFromJson(
            PoeNinjaLeague::class,
            $response->body(),
        );
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('poe.ninja.base_url'))
            ->acceptJson()
            ->withUserAgent((string) config('poe.ninja.user_agent'));
    }
}
