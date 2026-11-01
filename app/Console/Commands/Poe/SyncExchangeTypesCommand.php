<?php

namespace App\Console\Commands\Poe;

use App\Models\Poe\ExchangeType;
use App\Models\Poe\PoeVersion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
#[Signature('poe:sync-exchange-types
    {--path= : Path to the JSON file with exchange types, defaults to database/data/exchange_types.json}')]
#[Description('Upsert exchange types for every PoE version from a local JSON file')]
final class SyncExchangeTypesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $option = $this->option('path');

        $path = is_string($option)
            ? $option
            : database_path('data/exchange_types.json');

        if (! is_file($path)) {
            $this->error("Exchange types file [{$path}] does not exist.");

            return self::FAILURE;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            $this->error("Exchange types file [{$path}] could not be read.");

            return self::FAILURE;
        }

        $types = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($types)) {
            throw new RuntimeException("Exchange types file [{$path}] must contain a JSON object.");
        }

        /** @var array<string, int> $versionIds */
        $versionIds = PoeVersion::query()->pluck('id', 'version')->all();

        $upserted = 0;

        foreach ($types as $version => $entries) {
            $versionId = $versionIds[$version] ?? null;

            if ($versionId === null) {
                $this->warn("Skipping [{$version}]: there is no such row in poe_versions.");

                continue;
            }

            $rows = $this->buildRows($entries, $versionId);

            if ($rows === []) {
                continue;
            }

            ExchangeType::query()->upsert(
                $rows,
                uniqueBy: ['poe_version_id', 'value'],
                update: ['category'],
            );

            $upserted += count($rows);

            $this->line("Synced [{$version}]: ".count($rows).' exchange types.');
        }

        $this->info("Upserted {$upserted} exchange types.");

        return self::SUCCESS;
    }

    /**
     * @return list<array{poe_version_id: int, value: string, category: string}>
     */
    private function buildRows(mixed $entries, int $versionId): array
    {
        if (! is_array($entries)) {
            return [];
        }

        $rows = [];

        foreach ($entries as $entry) {
            if (! is_array($entry) || ! is_string($entry['value'] ?? null) || ! is_string($entry['category'] ?? null)) {
                continue;
            }

            $rows[] = [
                'poe_version_id' => $versionId,
                'value' => $entry['value'],
                'category' => $entry['category'],
            ];
        }

        return $rows;
    }
}
