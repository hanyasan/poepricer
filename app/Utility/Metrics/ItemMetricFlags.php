<?php

namespace App\Utility\Metrics;

/**
 * Reading rules of the item metrics shared by the items table and the item page.
 */
final readonly class ItemMetricFlags
{
    /**
     * Whether there are too few observations for the item metrics to be trusted.
     */
    public function isLowData(int $observations): bool
    {
        return $observations < (int) config('poe.metrics.items_table_min_observations');
    }

    /**
     * Whether the residual exceeds the item's own usual spread by the configured multiplier.
     */
    public function isUnusual(?float $residual, ?float $residualMad): bool
    {
        return $residual !== null
            && $residualMad !== null
            && $residualMad > 0
            && abs($residual) > (float) config('poe.overview.unusual_residual_multiplier') * $residualMad;
    }
}
