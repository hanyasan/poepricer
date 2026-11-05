<?php

namespace Tests\Feature\Services\DataServices\Compact;

use App\Repositories\Poe\Exchange\Compact\ExchangeCompactRepositoryContract;
use App\Repositories\Poe\Exchange\PriceCheck\PriceCheckRepositoryContract;
use App\Services\DataServices\Compact\ExchangeCompactService;
use Carbon\Carbon;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\FeatureTestCase;

class ExchangeCompactServiceTest extends FeatureTestCase
{
    private MockInterface&ExchangeCompactRepositoryContract $repository;

    private MockInterface&PriceCheckRepositoryContract $priceCheckRepository;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2030-01-10 15:45:00', 'UTC'));
        config()->set('poe.compact.days_stay', 7);

        $this->repository = Mockery::mock(ExchangeCompactRepositoryContract::class);
        $this->priceCheckRepository = Mockery::mock(PriceCheckRepositoryContract::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_does_nothing_when_there_are_no_price_checks_to_compact(): void
    {
        $this->repository->shouldReceive('getLeagueDailyPeriodsPriceCheckIds')->once()->andReturn([]);
        $this->repository->shouldNotReceive('getAvgCoreTypesData');
        $this->repository->shouldNotReceive('getAvgCorePricesData');
        $this->repository->shouldNotReceive('makeCompactPriceCheck');
        $this->priceCheckRepository->shouldNotReceive('deleteMany');

        $this->service()->compactPricesByBaseTimeRule();
    }

    public function test_requests_periods_older_than_configured_days_from_start_of_utc_day(): void
    {
        $this->repository
            ->shouldReceive('getLeagueDailyPeriodsPriceCheckIds')
            ->once()
            ->withArgs(fn (Carbon $date) => $date->equalTo(Carbon::parse('2030-01-03 00:00:00', 'UTC')))
            ->andReturn([]);

        $this->service()->compactPricesByBaseTimeRule();
    }

    public function test_compacts_and_deletes_price_checks_grouped_by_league_and_date(): void
    {
        $this->repository->shouldReceive('getLeagueDailyPeriodsPriceCheckIds')->once()->andReturn([
            ['id' => 1, 'poe_league_id' => 10, 'date' => '2030-01-01'],
            ['id' => 2, 'poe_league_id' => 10, 'date' => '2030-01-01'],
            ['id' => 3, 'poe_league_id' => 10, 'date' => '2030-01-02'],
            ['id' => 4, 'poe_league_id' => 20, 'date' => '2030-01-01'],
        ]);

        $groups = [
            [10, '2030-01-01', [1, 2]],
            [10, '2030-01-02', [3]],
            [20, '2030-01-01', [4]],
        ];

        foreach ($groups as [$leagueId, $date, $ids]) {
            $types = [['exchange_type_id' => 1, 'exchange_item_trade_id' => "t-$leagueId-$date", 'name' => 'Type', 'rate' => 1.5]];
            $prices = [['exchange_type_id' => 1, 'exchange_item_id' => $leagueId, 'rate' => 2.5]];

            $this->repository->shouldReceive('getAvgCoreTypesData')->once()->with($ids)->andReturn($types);
            $this->repository->shouldReceive('getAvgCorePricesData')->once()->with($ids)->andReturn($prices);
            $this->repository->shouldReceive('makeCompactPriceCheck')->once()->with($leagueId, $date, $types, $prices);
            $this->priceCheckRepository->shouldReceive('deleteMany')->once()->with($ids);
        }

        $this->service()->compactPricesByBaseTimeRule();
    }

    public function test_does_not_delete_price_checks_of_a_group_when_compacting_fails(): void
    {
        $this->repository->shouldReceive('getLeagueDailyPeriodsPriceCheckIds')->once()->andReturn([
            ['id' => 1, 'poe_league_id' => 10, 'date' => '2030-01-01'],
        ]);
        $this->repository->shouldReceive('getAvgCoreTypesData')->once()->andReturn([]);
        $this->repository->shouldReceive('getAvgCorePricesData')->once()->andReturn([]);
        $this->repository
            ->shouldReceive('makeCompactPriceCheck')
            ->once()
            ->andThrow(new RuntimeException('compact failed'));
        $this->priceCheckRepository->shouldNotReceive('deleteMany');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('compact failed');

        $this->service()->compactPricesByBaseTimeRule();
    }

    private function service(): ExchangeCompactService
    {
        return new ExchangeCompactService($this->repository, $this->priceCheckRepository);
    }
}
