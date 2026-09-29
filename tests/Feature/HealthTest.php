<?php

use App\Support\Health\Check;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('reports liveness', function () {
    $this->getJson('/healthz')
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);
});

it('reports readiness when every dependency is up', function () {
    $this->getJson('/readyz')
        ->assertOk()
        ->assertExactJson([
            'status' => 'ok',
            'checks' => ['database' => 'ok', 'cache' => 'ok'],
        ]);
});

it('fails readiness with 503 when the database is down', function () {
    DB::shouldReceive('select')->once()->andThrow(new RuntimeException('connection refused: secret-host'));

    $this->getJson('/readyz')
        ->assertStatus(503)
        ->assertExactJson([
            'status' => 'fail',
            'checks' => ['database' => 'fail', 'cache' => 'ok'],
        ]);
});

it('fails readiness when the cache throws', function () {
    Cache::shouldReceive('put')->once()->andThrow(new RuntimeException('redis down'));

    $this->getJson('/readyz')
        ->assertStatus(503)
        ->assertJsonPath('checks.cache', 'fail')
        ->assertJsonPath('checks.database', 'ok');
});

it('fails readiness when the cache does not return what was written', function () {
    Cache::shouldReceive('put')->once();
    Cache::shouldReceive('get')->once()->andReturnNull();
    Cache::shouldReceive('forget')->once();

    $this->getJson('/readyz')
        ->assertStatus(503)
        ->assertJsonPath('checks.cache', 'fail');
});

it('never leaks error details in the readiness response', function () {
    DB::shouldReceive('select')->once()->andThrow(new RuntimeException('password=hunter2'));

    expect($this->getJson('/readyz')->getContent())->not->toContain('hunter2');
});

it('includes any check registered in config', function () {
    $failing = new class implements Check
    {
        public function passes(): bool
        {
            return false;
        }
    };
    $this->app->instance($failing::class, $failing);
    config(['health.checks' => ['custom' => $failing::class]]);

    $this->getJson('/readyz')
        ->assertStatus(503)
        ->assertExactJson(['status' => 'fail', 'checks' => ['custom' => 'fail']]);
});
