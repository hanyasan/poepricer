<?php

namespace Tests\Feature\Services\NinjaImport;

use App\DataTransferObjects\RepositoryObjects\Exchange\PriceCheckDto;
use App\DataTransferObjects\RepositoryObjects\League\ExtendedPoeLeagueDto;
use App\DataTransferObjects\RepositoryObjects\League\LeagueCheckRuleDto;
use App\DataTransferObjects\RepositoryObjects\PoeVersionDto;
use App\Repositories\Poe\PoeLeagueRepositoryContract;
use App\Services\NinjaImport\NinjaImportServiceContract;
use App\Services\NinjaImport\NinjaLeaguePricerService;
use Carbon\CarbonImmutable;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\FeatureTestCase;

class NinjaLeaguePricerServiceTest extends FeatureTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string, array{string, string, string, int, bool}>
     */
    public static function dueCases(): array
    {
        return [
            'late created_at still due on the slot tick' => ['poe1', '2026-11-09 12:09:30', '2026-11-09 12:20:05', 20, true],
            'created_at at the slot start' => ['poe1', '2026-11-09 12:00:20', '2026-11-09 12:20:00', 20, true],
            'too early, one tick passed' => ['poe1', '2026-11-09 12:03:00', '2026-11-09 12:10:00', 20, false],
            'ten minute rule is due every tick' => ['poe1', '2026-11-09 12:03:00', '2026-11-09 12:10:00', 10, true],
            'poe2 uses the thirty minute step' => ['poe2', '2026-11-09 12:25:00', '2026-11-09 12:30:00', 30, true],
            'poe2 not due before the slot ends' => ['poe2', '2026-11-09 12:25:00', '2026-11-09 12:20:00', 30, false],
        ];
    }

    #[DataProvider('dueCases')]
    public function test_it_picks_leagues_by_schedule_slot(
        string $version,
        string $latestCheck,
        string $now,
        int $minutes,
        bool $isDue,
    ): void {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($now, 'UTC'));

        $picked = $this->service($this->league($version, $latestCheck, $minutes))
            ->pickLeaguesForCheck(new PoeVersionDto(1, $version));

        $this->assertCount($isDue ? 1 : 0, $picked);
    }

    public function test_it_picks_league_without_price_checks_and_skips_league_without_rule(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-09 12:20:00', 'UTC'));

        $service = $this->service(
            $this->league('poe1', null, 20),
            $this->league('poe1', null, null),
        );

        $this->assertCount(1, $service->pickLeaguesForCheck(new PoeVersionDto(1, 'poe1')));
    }

    private function league(string $version, ?string $latestCheck, ?int $minutes): ExtendedPoeLeagueDto
    {
        return new ExtendedPoeLeagueDto(
            id: 1,
            league_name: 'Allflame',
            ninja_league_id: 'Allflame',
            is_active: true,
            poe_version_id: 1,
            poe_version: new PoeVersionDto(1, $version),
            check_rule: new LeagueCheckRuleDto(1, $minutes, 1),
            latest_price_check: $latestCheck === null ? [] : [new PriceCheckDto(1, $latestCheck, 1)],
        );
    }

    private function service(ExtendedPoeLeagueDto ...$leagues): NinjaLeaguePricerService
    {
        $repository = $this->mock(PoeLeagueRepositoryContract::class, function (MockInterface $mock) use ($leagues): void {
            $mock->shouldReceive('getWithLastPriceCheckAndRule')->andReturn(array_values($leagues));
        });

        return new NinjaLeaguePricerService($this->mock(NinjaImportServiceContract::class), $repository);
    }
}
