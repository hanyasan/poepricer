<?php

namespace Tests\Feature\Services\DataServices;

use App\DataTransferObjects\RepositoryObjects\PoeVersionDto;
use App\Models\Poe\PoeVersion;
use App\Services\DataServices\PoeVersionService;
use Tests\FeatureTestCase;

class PoeVersionServiceTest extends FeatureTestCase
{
    public function test_returns_a_dto_for_every_poe_version(): void
    {
        $result = $this->service()->getAll();

        $this->assertCount(PoeVersion::query()->count(), $result);
        $this->assertContainsOnlyInstancesOf(PoeVersionDto::class, $result);

        $poe1 = $this->findByVersion($result, 'poe1');

        $this->assertSame($this->versionId('poe1'), $poe1->id);
        $this->assertSame('poe1', $poe1->version);
        $this->assertSame($this->versionId('poe2'), $this->findByVersion($result, 'poe2')->id);
    }

    public function test_second_call_does_not_see_a_version_changed_after_the_first_call(): void
    {
        $poe2Id = $this->versionId('poe2');

        $first = $this->service()->getAll();

        PoeVersion::query()->whereKey($poe2Id)->update(['version' => 'poe2-renamed']);

        $second = $this->service()->getAll();

        $this->assertSame('poe2', $this->findByVersion($first, 'poe2')->version);
        $this->assertSame('poe2', $this->findByVersion($second, 'poe2')->version);
    }

    private function service(): PoeVersionService
    {
        return $this->app->make(PoeVersionService::class);
    }

    private function versionId(string $version): int
    {
        return (int) PoeVersion::query()->where('version', $version)->value('id');
    }

    /**
     * @param  PoeVersionDto[]  $dtos
     */
    private function findByVersion(array $dtos, string $version): PoeVersionDto
    {
        foreach ($dtos as $dto) {
            if ($dto->version === $version) {
                return $dto;
            }
        }

        $this->fail("No poe version dto with version [{$version}] was returned.");
    }
}
