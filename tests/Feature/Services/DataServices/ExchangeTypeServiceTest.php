<?php

namespace Tests\Feature\Services\DataServices;

use App\DataTransferObjects\RepositoryObjects\ExchangeTypeDto;
use App\Models\Poe\Exchange\ExchangeType;
use App\Models\Poe\PoeVersion;
use App\Services\DataServices\ExchangeTypeService;
use Illuminate\Support\Facades\Cache;
use Tests\FeatureTestCase;

class ExchangeTypeServiceTest extends FeatureTestCase
{
    public function test_returns_dtos_for_the_requested_version_only(): void
    {
        $poe1Id = $this->versionId('poe1');
        $poe2Id = $this->versionId('poe2');

        $this->deleteExchangeTypes($poe1Id, $poe2Id);

        $currency = $this->createExchangeType($poe1Id, 'Currency', 'Currency');
        $this->createExchangeType($poe1Id, 'Essence', 'Essences');
        $this->createExchangeType($poe2Id, 'Runes', 'Runes');

        $result = $this->service()->getByVersion($poe1Id);

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(ExchangeTypeDto::class, $result);

        $currencyDto = $this->findByValue($result, 'Currency');

        $this->assertSame($currency->id, $currencyDto->id);
        $this->assertSame('Currency', $currencyDto->category);
        $this->assertSame('Currency', $currencyDto->value);
        $this->assertSame($poe1Id, $currencyDto->poe_version_id);
    }

    public function test_returns_empty_array_when_the_version_has_no_exchange_types(): void
    {
        $poe2Id = $this->versionId('poe2');

        $this->deleteExchangeTypes($poe2Id);

        $result = $this->service()->getByVersion($poe2Id);

        $this->assertSame([], $result);
    }

    public function test_second_call_does_not_see_records_created_after_the_first_call(): void
    {
        $poe1Id = $this->versionId('poe1');

        $this->deleteExchangeTypes($poe1Id);
        $this->createExchangeType($poe1Id, 'Currency', 'Currency');

        $first = $this->service()->getByVersion($poe1Id);

        $this->createExchangeType($poe1Id, 'Essence', 'Essences');

        $second = $this->service()->getByVersion($poe1Id);

        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertSame('Currency', $second[0]->value);
    }

    public function test_returns_fresh_dtos_after_the_version_cache_tag_is_flushed(): void
    {
        $poe1Id = $this->versionId('poe1');

        $this->deleteExchangeTypes($poe1Id);
        $this->createExchangeType($poe1Id, 'Currency', 'Currency');

        $this->service()->getByVersion($poe1Id);

        $this->createExchangeType($poe1Id, 'Essence', 'Essences');

        Cache::tags(['poe_version'.$poe1Id])->flush();

        $result = $this->service()->getByVersion($poe1Id);

        $this->assertCount(2, $result);
    }

    private function service(): ExchangeTypeService
    {
        return $this->app->make(ExchangeTypeService::class);
    }

    private function versionId(string $version): int
    {
        return (int) PoeVersion::query()->where('version', $version)->value('id');
    }

    private function deleteExchangeTypes(int ...$versionIds): void
    {
        ExchangeType::query()->whereIn('poe_version_id', $versionIds)->delete();
    }

    private function createExchangeType(int $versionId, string $value, string $category): ExchangeType
    {
        return ExchangeType::query()->create([
            'poe_version_id' => $versionId,
            'value' => $value,
            'category' => $category,
        ]);
    }

    /**
     * @param  ExchangeTypeDto[]  $dtos
     */
    private function findByValue(array $dtos, string $value): ExchangeTypeDto
    {
        foreach ($dtos as $dto) {
            if ($dto->value === $value) {
                return $dto;
            }
        }

        $this->fail("No exchange type dto with value [{$value}] was returned.");
    }
}
