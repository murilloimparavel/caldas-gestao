<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;

it('reports the application as healthy', function () {
    config(['session.driver' => 'redis']);

    $this->getJson(route('health.app'))
        ->assertOk()
        ->assertExactJson(['status' => 'ok', 'service' => 'app']);
});

it('reports the database as healthy', function () {
    config(['session.driver' => 'redis']);

    $this->getJson(route('health.database'))
        ->assertOk()
        ->assertExactJson(['status' => 'ok', 'service' => 'database']);
});

it('reports redis as healthy when it responds', function () {
    $connection = Mockery::mock();
    $connection->shouldReceive('ping')->once()->andReturn('PONG');

    Redis::shouldReceive('connection')->once()->andReturn($connection);

    $this->getJson(route('health.redis'))
        ->assertOk()
        ->assertExactJson(['status' => 'ok', 'service' => 'redis']);
});

it('reports redis as unhealthy when it is unavailable', function () {
    $connection = Mockery::mock();
    $connection->shouldReceive('ping')->once()->andThrow(new RuntimeException('Redis unavailable'));

    Redis::shouldReceive('connection')->once()->andReturn($connection);

    $this->getJson(route('health.redis'))
        ->assertServiceUnavailable()
        ->assertExactJson(['status' => 'unhealthy', 'service' => 'redis']);
});

it('reports a database failure as sanitized json', function () {
    DB::shouldReceive('connection')->once()->andThrow(new RuntimeException('Database unavailable'));

    $this->getJson(route('health.database'))
        ->assertServiceUnavailable()
        ->assertExactJson(['status' => 'unhealthy', 'service' => 'database']);
});
