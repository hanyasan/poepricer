<?php

namespace Tests\Feature\HttpClients;

use App\DataTransferObjects\ParsingObjects\Exchange\PoeNinjaExchangeCore;
use App\DataTransferObjects\ParsingObjects\Exchange\PoeNinjaExchangeItem;
use App\DataTransferObjects\ParsingObjects\Exchange\PoeNinjaExchangeLine;
use App\DataTransferObjects\ParsingObjects\Exchange\PoeNinjaExchangeResponse;
use App\DataTransferObjects\ParsingObjects\PoeNinjaLeague;
use App\Exceptions\Mapping\MapInvalidSourceException;
use App\Exceptions\Mapping\MappingErrorException;
use App\HttpClients\NinjaClient;
use App\Utility\Mapper\MapperServiceContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\FeatureTestCase;

class NinjaClientTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function client(): NinjaClient
    {
        return $this->app->make(NinjaClient::class);
    }

    public function test_it_requests_exchange_prices_for_the_given_version_league_and_type(): void
    {
        Http::fake(['poe.ninja/*' => $this->exchangePayload()]);

        $this->client()->getExchangeTypePrices('poe1', 'settlers', 'Currency');

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && $request->url() === 'https://poe.ninja/poe1/api/economy/exchange/current/overview?league=settlers&type=Currency'
                && $request->hasHeader('Accept', 'application/json')
                && $request->hasHeader('User-Agent', config('poe.ninja.user_agent'));
        });
    }

    public function test_it_maps_the_exchange_response_into_dtos(): void
    {
        Http::fake(['poe.ninja/*' => $this->exchangePayload()]);

        $result = $this->client()->getExchangeTypePrices('poe1', 'settlers', 'Currency');

        $this->assertInstanceOf(PoeNinjaExchangeResponse::class, $result);
        $this->assertSame(['chaos' => 1.0, 'divine' => 0.0089], $result->core->rates);

        $this->assertCount(1, $result->core->items);
        $this->assertInstanceOf(PoeNinjaExchangeItem::class, $result->core->items[0]);
        $this->assertSame('chaos', $result->core->items[0]->id);

        $this->assertCount(2, $result->lines);
        $this->assertInstanceOf(PoeNinjaExchangeLine::class, $result->lines[0]);
        $this->assertSame(12.5, $result->lines[0]->primaryValue);
        $this->assertNull($result->lines[1]->primaryValue);
    }

    public function test_it_passes_the_raw_exchange_body_to_the_mapper(): void
    {
        $payload = $this->exchangePayload();

        Http::fake(['poe.ninja/*' => Http::response(json_encode($payload, JSON_THROW_ON_ERROR))]);

        $mapper = $this->mock(MapperServiceContract::class);
        $mapper->expects('mapFromJson')
            ->once()
            ->withArgs(function (string $dtoClass, string $data) use ($payload): bool {
                $this->assertSame(PoeNinjaExchangeResponse::class, $dtoClass);
                $this->assertEquals($payload, json_decode($data, true, JSON_THROW_ON_ERROR));

                return true;
            })
            ->andReturn(new PoeNinjaExchangeResponse(new PoeNinjaExchangeCore([], []), [], []));

        $this->client()->getExchangeTypePrices('poe1', 'settlers', 'Currency');
    }

    public function test_it_throws_a_mapping_error_when_the_exchange_payload_does_not_match_the_dto(): void
    {
        Http::fake(['poe.ninja/*' => ['unexpected' => 'shape']]);

        $this->expectException(MappingErrorException::class);

        $this->client()->getExchangeTypePrices('poe1', 'settlers', 'Currency');
    }

    public function test_it_throws_an_invalid_source_error_when_the_exchange_body_is_not_json(): void
    {
        Http::fake(['poe.ninja/*' => Http::response('<html>not json</html>', 200)]);

        $this->expectException(MapInvalidSourceException::class);

        $this->client()->getExchangeTypePrices('poe1', 'settlers', 'Currency');
    }

    public function test_it_throws_a_request_error_when_the_exchange_endpoint_fails(): void
    {
        Http::fake(['poe.ninja/*' => Http::response(['error' => 'boom'], 500)]);

        $this->expectException(RequestException::class);

        $this->client()->getExchangeTypePrices('poe1', 'settlers', 'Currency');
    }

    public function test_it_requests_leagues_for_the_given_version(): void
    {
        Http::fake(['poe.ninja/*' => $this->leaguesPayload()]);

        $this->client()->getEconomyLeagues('poe2');

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && $request->url() === 'https://poe.ninja/poe2/api/economy/leagues'
                && $request->hasHeader('Accept', 'application/json')
                && $request->hasHeader('User-Agent', config('poe.ninja.user_agent'));
        });
    }

    public function test_it_maps_the_leagues_response_into_dtos(): void
    {
        Http::fake(['poe.ninja/*' => $this->leaguesPayload()]);

        $result = $this->client()->getEconomyLeagues('poe1');

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(PoeNinjaLeague::class, $result);
        $this->assertSame('Settlers', $result[0]->name);
        $this->assertSame('settlers', $result[0]->id);
        $this->assertSame('standard', $result[1]->id);
    }

    public function test_it_returns_an_empty_array_when_there_are_no_leagues(): void
    {
        Http::fake(['poe.ninja/*' => Http::response([], 200)]);

        $this->assertSame([], $this->client()->getEconomyLeagues('poe1'));
    }

    public function test_it_passes_the_raw_leagues_body_to_the_array_mapper(): void
    {
        $payload = $this->leaguesPayload();

        Http::fake(['poe.ninja/*' => Http::response(json_encode($payload, JSON_THROW_ON_ERROR))]);

        $mapper = $this->mock(MapperServiceContract::class);
        $mapper->expects('mapArrayFromJson')
            ->once()
            ->withArgs(function (string $dtoClass, string $data) use ($payload): bool {
                $this->assertSame(PoeNinjaLeague::class, $dtoClass);
                $this->assertEquals($payload, json_decode($data, true, JSON_THROW_ON_ERROR));

                return true;
            })
            ->andReturn([]);

        $this->client()->getEconomyLeagues('poe1');
    }

    public function test_it_throws_a_mapping_error_when_one_league_is_invalid(): void
    {
        Http::fake(['poe.ninja/*' => [['name' => 'Settlers', 'id' => 'settlers'], ['name' => 'Standard']]]);

        $this->expectException(MappingErrorException::class);

        $this->client()->getEconomyLeagues('poe1');
    }

    public function test_it_throws_a_request_error_when_the_leagues_endpoint_fails(): void
    {
        Http::fake(['poe.ninja/*' => Http::response('Not Found', 404)]);

        $this->expectException(RequestException::class);

        $this->client()->getEconomyLeagues('poe1');
    }

    public function test_it_throws_a_connection_error_when_the_transport_fails(): void
    {
        Http::fake(['poe.ninja/*' => fn () => throw new ConnectionException('Connection refused')]);

        $this->expectException(ConnectionException::class);

        $this->client()->getEconomyLeagues('poe1');
    }

    public function test_it_does_not_call_the_mapper_when_the_request_fails(): void
    {
        Http::fake(['poe.ninja/*' => Http::response(['error' => 'boom'], 503)]);

        $mapper = $this->mock(MapperServiceContract::class);
        $mapper->expects('mapFromJson')->never();

        try {
            $this->client()->getExchangeTypePrices('poe1', 'settlers', 'Currency');
        } catch (RequestException) {
            // ожидаемо
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function exchangePayload(): array
    {
        return [
            'core' => [
                'items' => [
                    ['id' => 'chaos', 'name' => 'Chaos Orb'],
                ],
                'rates' => ['chaos' => 1.0, 'divine' => 0.0089],
            ],
            'lines' => [
                ['id' => 'line-1', 'primaryValue' => 12.5],
                ['id' => 'line-2'],
            ],
            'items' => [
                ['id' => 'item-1', 'name' => 'Divine Orb'],
            ],
        ];
    }

    /**
     * @return list<array{name: string, id: string}>
     */
    private function leaguesPayload(): array
    {
        return [
            ['name' => 'Settlers', 'id' => 'settlers'],
            ['name' => 'Standard', 'id' => 'standard'],
        ];
    }
}
