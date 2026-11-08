<?php

namespace App\Utility\Metrics;

use App\DataTransferObjects\ServiceObjects\MarketDistribution\MarketBinDto;
use App\DataTransferObjects\ServiceObjects\MarketDistribution\MarketBreadthDto;
use InvalidArgumentException;

final readonly class ReturnDistribution
{
    private const int BIN_PRECISION = 6;

    /**
     * Counts the returns that grew, fell and stayed within the threshold of zero.
     *
     * @param  array<int, float>  $returns
     */
    public function breadth(array $returns, float $flatThreshold): MarketBreadthDto
    {
        $up = 0;
        $down = 0;
        $flat = 0;

        foreach ($returns as $return) {
            if (abs($return) < $flatThreshold) {
                $flat++;
            } elseif ($return > 0.0) {
                $up++;
            } else {
                $down++;
            }
        }

        return new MarketBreadthDto($up, $down, $flat);
    }

    /**
     * Histogram of the returns. Equal bins of the width cover [-range, range), the
     * two open bins on the sides take the tails. Empty bins are returned too.
     *
     * @param  array<int, float>  $returns
     * @return MarketBinDto[]
     *
     * @throws InvalidArgumentException when the width or the range is not positive or the range is not a whole number of bins
     */
    public function bins(array $returns, float $width, float $range): array
    {
        if ($width <= 0.0 || $range <= 0.0) {
            throw new InvalidArgumentException('Bin width and range must be positive.');
        }

        $middleCount = (int) round(2.0 * $range / $width);

        if (abs((float) $middleCount * $width - 2.0 * $range) > 1e-9) {
            throw new InvalidArgumentException('The range must hold a whole number of bins.');
        }

        $counts = array_fill(0, $middleCount + 2, 0);

        foreach ($returns as $return) {
            $counts[$this->binIndex($return, $width, $range, $middleCount)]++;
        }

        $bins = [new MarketBinDto(null, -$range, $counts[0])];

        for ($index = 0; $index < $middleCount; $index++) {
            $bins[] = new MarketBinDto(
                round(-$range + (float) $index * $width, self::BIN_PRECISION),
                round(-$range + (float) ($index + 1) * $width, self::BIN_PRECISION),
                $counts[$index + 1],
            );
        }

        $bins[] = new MarketBinDto($range, null, $counts[$middleCount + 1]);

        return $bins;
    }

    /**
     * Index in the counts: 0 is the lower tail, the last one is the upper tail.
     */
    private function binIndex(float $return, float $width, float $range, int $middleCount): int
    {
        if ($return < -$range) {
            return 0;
        }

        if ($return >= $range) {
            return $middleCount + 1;
        }

        $position = (int) floor(round(($return + $range) / $width, 9));

        return 1 + min($position, $middleCount - 1);
    }
}
