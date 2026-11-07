<?php

namespace Tests\Feature;

use App\Jobs\Fetch\FetchPoeOnePricesJob;
use App\Jobs\Fetch\FetchPoeTwoPricesJob;
use Illuminate\Support\Facades\Queue;
use Tests\FeatureTestCase;

class FetchAllPricesCommandFeatureTest extends FeatureTestCase
{
    public function test_it_dispatches_the_fetch_jobs_of_both_poe_versions(): void
    {
        Queue::fake();

        $this->artisan('poe:fetch-all')
            ->expectsOutputToContain('Price fetch jobs are queued.')
            ->assertSuccessful();

        Queue::assertPushedOn('scheduled', FetchPoeOnePricesJob::class);
        Queue::assertPushedOn('scheduled', FetchPoeTwoPricesJob::class);
        Queue::assertPushed(FetchPoeOnePricesJob::class, 1);
        Queue::assertPushed(FetchPoeTwoPricesJob::class, 1);
    }
}
