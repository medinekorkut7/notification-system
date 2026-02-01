<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Redis;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $apiKey = trim((string) explode(',', (string) env('API_KEYS'))[0]);
        if ($apiKey !== '') {
            $this->withHeaders(['X-Api-Key' => $apiKey]);
        }

        Redis::shouldReceive('eval')->andReturn(1)->byDefault();
        Redis::shouldReceive('incr')->andReturn(1)->byDefault();
        Redis::shouldReceive('expire')->andReturn(true)->byDefault();
        Redis::shouldReceive('exists')->andReturn(0)->byDefault();
        Redis::shouldReceive('ping')->andReturn('PONG')->byDefault();
        Redis::shouldReceive('set')->andReturn(true)->byDefault();
        Redis::shouldReceive('get')->andReturn(null)->byDefault();
        Redis::shouldReceive('del')->andReturn(1)->byDefault();
    }
}
