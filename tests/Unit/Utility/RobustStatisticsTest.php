<?php

namespace Tests\Unit\Utility;

use App\Utility\Statistics\RobustStatistics;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RobustStatisticsTest extends TestCase
{
    public function test_median_of_an_odd_number_of_values_is_the_middle_one(): void
    {
        $this->assertSame(3.0, $this->statistics()->median([5.0, 1.0, 3.0]));
    }

    public function test_median_of_an_even_number_of_values_is_the_mean_of_the_middle_two(): void
    {
        $this->assertSame(2.5, $this->statistics()->median([4.0, 1.0, 3.0, 2.0]));
    }

    public function test_median_ignores_the_keys_of_the_values(): void
    {
        $this->assertSame(2.0, $this->statistics()->median([10 => 3.0, 20 => 1.0, 30 => 2.0]));
    }

    public function test_median_of_no_values_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->statistics()->median([]);
    }

    public function test_scaled_mad_is_the_scaled_median_of_absolute_deviations(): void
    {
        // median 3, deviations [2, 1, 0, 1, 2] -> median 1
        $this->assertEqualsWithDelta(1.4826, $this->statistics()->scaledMad([1.0, 2.0, 3.0, 4.0, 5.0]), 1e-9);
    }

    public function test_scaled_mad_is_not_moved_by_a_single_outlier(): void
    {
        $withoutOutlier = $this->statistics()->scaledMad([1.0, 2.0, 3.0, 4.0, 5.0]);
        $withOutlier = $this->statistics()->scaledMad([1.0, 2.0, 3.0, 4.0, 1000.0]);

        $this->assertEqualsWithDelta($withoutOutlier, $withOutlier, 1.5);
    }

    public function test_scaled_mad_of_constant_values_is_zero(): void
    {
        $this->assertSame(0.0, $this->statistics()->scaledMad([2.0, 2.0, 2.0]));
    }

    public function test_theil_sen_recovers_the_slope_of_a_line(): void
    {
        $slope = $this->statistics()->theilSenSlope([0.0, 1.0, 2.0, 3.0], [1.0, 3.0, 5.0, 7.0]);

        $this->assertEqualsWithDelta(2.0, $slope, 1e-9);
    }

    public function test_theil_sen_resists_an_outlier(): void
    {
        $slope = $this->statistics()->theilSenSlope(
            [0.0, 1.0, 2.0, 3.0, 4.0, 5.0],
            [0.0, 1.0, 2.0, 3.0, 4.0, 100.0],
        );

        $this->assertEqualsWithDelta(1.0, $slope, 1e-9);
    }

    public function test_theil_sen_is_undefined_when_x_never_changes(): void
    {
        $this->assertNull($this->statistics()->theilSenSlope([1.0, 1.0, 1.0], [1.0, 2.0, 3.0]));
    }

    public function test_squared_correlation_of_a_perfect_line_is_one(): void
    {
        $this->assertEqualsWithDelta(
            1.0,
            $this->statistics()->squaredCorrelation([1.0, 2.0, 3.0], [-2.0, -4.0, -6.0]),
            1e-9,
        );
    }

    public function test_squared_correlation_of_unrelated_series_is_zero(): void
    {
        $this->assertEqualsWithDelta(
            0.0,
            $this->statistics()->squaredCorrelation([1.0, 2.0, 3.0, 4.0, 5.0], [1.0, -1.0, 0.0, -1.0, 1.0]),
            1e-9,
        );
    }

    public function test_squared_correlation_is_undefined_for_a_constant_series_or_a_single_point(): void
    {
        $this->assertNull($this->statistics()->squaredCorrelation([1.0, 2.0, 3.0], [4.0, 4.0, 4.0]));
        $this->assertNull($this->statistics()->squaredCorrelation([1.0], [1.0]));
    }

    private function statistics(): RobustStatistics
    {
        return new RobustStatistics;
    }
}
