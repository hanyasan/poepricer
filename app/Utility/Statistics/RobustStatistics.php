<?php

namespace App\Utility\Statistics;

use InvalidArgumentException;

final readonly class RobustStatistics
{
    /**
     * Scales the median absolute deviation to match the standard deviation of normal data.
     */
    private const float MAD_SCALE = 1.4826;

    private const float MIN_SPREAD = 1e-12;

    /**
     * @param  array<int, float>  $values
     *
     * @throws InvalidArgumentException when no values are given
     */
    public function median(array $values): float
    {
        $count = count($values);

        if ($count === 0) {
            throw new InvalidArgumentException('Median of an empty set is undefined.');
        }

        $sorted = array_values($values);
        sort($sorted);

        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return $sorted[$middle];
        }

        return ($sorted[$middle - 1] + $sorted[$middle]) / 2.0;
    }

    /**
     * Median absolute deviation, scaled to be comparable with the standard deviation.
     *
     * @param  array<int, float>  $values
     *
     * @throws InvalidArgumentException when no values are given
     */
    public function scaledMad(array $values): float
    {
        $median = $this->median($values);

        return self::MAD_SCALE * $this->median(array_map(
            static fn (float $value): float => abs($value - $median),
            $values,
        ));
    }

    /**
     * Theil-Sen estimator: the median of the slopes between every pair of points
     * with a distinct x. Returns null when no such pair exists.
     *
     * @param  array<int, float>  $x
     * @param  array<int, float>  $y
     */
    public function theilSenSlope(array $x, array $y): ?float
    {
        $x = array_values($x);
        $y = array_values($y);
        $count = min(count($x), count($y));
        $slopes = [];

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $deltaX = $x[$j] - $x[$i];

                if (abs($deltaX) < self::MIN_SPREAD) {
                    continue;
                }

                $slopes[] = ($y[$j] - $y[$i]) / $deltaX;
            }
        }

        return $slopes === [] ? null : $this->median($slopes);
    }

    /**
     * Squared Pearson correlation. Returns null when fewer than two points
     * are given or either series is constant.
     *
     * @param  array<int, float>  $x
     * @param  array<int, float>  $y
     */
    public function squaredCorrelation(array $x, array $y): ?float
    {
        $x = array_values($x);
        $y = array_values($y);
        $count = min(count($x), count($y));

        if ($count < 2) {
            return null;
        }

        $meanX = array_sum(array_slice($x, 0, $count)) / (float) $count;
        $meanY = array_sum(array_slice($y, 0, $count)) / (float) $count;
        $covariance = 0.0;
        $varianceX = 0.0;
        $varianceY = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $covariance += ($x[$i] - $meanX) * ($y[$i] - $meanY);
            $varianceX += ($x[$i] - $meanX) ** 2.0;
            $varianceY += ($y[$i] - $meanY) ** 2.0;
        }

        if ($varianceX < self::MIN_SPREAD || $varianceY < self::MIN_SPREAD) {
            return null;
        }

        return ($covariance ** 2.0) / ($varianceX * $varianceY);
    }
}
