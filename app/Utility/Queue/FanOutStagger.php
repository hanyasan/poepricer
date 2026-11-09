<?php

namespace App\Utility\Queue;

/**
 * Spreads the jobs of one fan-out over time, so a single scheduled tick does not
 * send every league to the workers at the same moment.
 */
final readonly class FanOutStagger
{
    /**
     * Delay of the job at the given position of the fan-out, in seconds.
     */
    public function delayFor(int $index): int
    {
        $seconds = max(0, (int) config('poe.queue.fan_out_stagger_seconds'));

        return max(0, $index) * $seconds;
    }
}
