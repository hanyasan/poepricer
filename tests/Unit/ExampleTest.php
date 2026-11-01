<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_that_true_is_true(): void
    {
        Cache::put('key', 'value');
        $this->assertTrue(true);
    }
}
