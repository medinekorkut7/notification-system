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

        if (!class_exists('Redis')) {
            Redis::shouldReceive('incr')->andReturn(1)->byDefault();
            Redis::shouldReceive('expire')->andReturn(true)->byDefault();
            Redis::shouldReceive('exists')->andReturn(0)->byDefault();
            Redis::shouldReceive('ping')->andReturn('PONG')->byDefault();
        }
    }
}
