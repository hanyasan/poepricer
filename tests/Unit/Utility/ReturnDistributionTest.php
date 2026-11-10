<?php

namespace Tests\Unit\Utility;

use App\Utility\Metrics\ReturnDistribution;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ReturnDistributionTest extends TestCase
{
    public function test_breadth_splits_returns_by_the_flat_threshold(): void
    {
        $breadth = $this->distribution()->breadth([0.02, 0.005, 0.004, 0.0, -0.004, -0.005, -0.3], 0.005);

        $this->assertSame(2, $breadth->up);
        $this->assertSame(2, $breadth->down);
        $this->assertSame(3, $breadth->flat);
    }

    public function test_breadth_of_no_returns_is_zero(): void
    {
        $breadth = $this->distribution()->breadth([], 0.005);

        $this->assertSame([0, 0, 0], [$breadth->up, $breadth->down, $breadth->flat]);
    }

    public function test_bins_have_open_tails_and_equal_middle_bins(): void
    {
        $bins = $this->distribution()->bins([], 0.02, 0.30);

        $this->assertCount(32, $bins);
        $this->assertNull($bins[0]->from);
        $this->assertSame(-0.3, $bins[0]->to);
        $this->assertSame([-0.3, -0.28], [$bins[1]->from, $bins[1]->to]);
        $this->assertSame([-0.02, 0.0], [$bins[15]->from, $bins[15]->to]);
        $this->assertSame([0.28, 0.3], [$bins[30]->from, $bins[30]->to]);
        $this->assertSame(0.3, $bins[31]->from);
        $this->assertNull($bins[31]->to);
    }

    public function test_returns_are_counted_in_the_bin_that_holds_them(): void
    {
        $bins = $this->distribution()->bins([-0.5, -0.1, -0.0951, 0.0, 0.0099, 0.01, 0.0999, 0.1, 0.7], 0.02, 0.30);
        $counts = array_map(static fn ($bin): int => $bin->count, $bins);

        $this->assertSame(1, $counts[0]);
        $this->assertSame(2, $counts[11]);
        $this->assertSame(3, $counts[16]);
        $this->assertSame(1, $counts[20]);
        $this->assertSame(1, $counts[21]);
        $this->assertSame(1, $counts[31]);
    }

    public function test_bin_counts_add_up_to_the_number_of_returns(): void
    {
        $returns = [-0.4, -0.05, -0.0301, 0.0, 0.0001, 0.033, 0.0777, 0.09999, 0.1, 0.35];
        $bins = $this->distribution()->bins($returns, 0.02, 0.30);

        $this->assertSame(count($returns), array_sum(array_map(static fn ($bin): int => $bin->count, $bins)));
    }

    public function test_a_range_that_is_not_a_whole_number_of_bins_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->distribution()->bins([0.0], 0.03, 0.10);
    }

    public function test_a_non_positive_width_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->distribution()->bins([0.0], 0.0, 0.10);
    }

    private function distribution(): ReturnDistribution
    {
        return new ReturnDistribution;
    }
}
