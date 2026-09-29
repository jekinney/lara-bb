<?php

use Illuminate\Support\Facades\File;

it('sends every page to the installer until laraBB is installed', function () {
    uninstall();

    $this->get('/')->assertRedirect('/install');
});

it('keeps the health checks reachable before installing', function () {
    uninstall();

    $this->get('/healthz')->assertOk();
});

it('answers 404 for the installer once installed, whatever the method', function () {
    $this->get('/install')->assertNotFound();
    $this->get('/install/token')->assertNotFound();
    $this->post('/install/run')->assertNotFound();
    $this->get('/install/run')->assertNotFound();
    $this->get('/install/anything/else')->assertNotFound();
});

it('sends a browser that opens the install action back to the review', function () {
    uninstall();

    $this->get('/install/run')->assertRedirect(route('install.review'));
});

it('serves the rest of the site normally once installed', function () {
    $this->get('/')->assertOk();
});

it('uses a throwaway key for the wizard session when there is no APP_KEY', function () {
    uninstall();
    config(['app.key' => '']);

    $this->get('/install/token')->assertOk();

    expect(sandboxPath('install-key'))->toBeFile()
        ->and(config('app.key'))->toStartWith('base64:');
});

it('reuses the throwaway key on later requests', function () {
    uninstall();
    config(['app.key' => '']);
    File::put(sandboxPath('install-key'), "base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=\n");

    $this->get('/install/token')->assertOk();

    expect(config('app.key'))->toBe('base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
});

it('leaves a real APP_KEY alone', function () {
    uninstall();
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    $this->get('/install/token')->assertOk();

    expect(sandboxPath('install-key'))->not->toBeFile();
});

it('switches to file sessions and cache while not installed', function () {
    uninstall();
    config(['larabb.install.session_driver' => 'file', 'larabb.install.cache_store' => 'file']);

    $this->get('/install/token')->assertOk();

    expect(config('session.driver'))->toBe('file')
        ->and(config('session.encrypt'))->toBeTrue()
        ->and(config('cache.default'))->toBe('file');
});
