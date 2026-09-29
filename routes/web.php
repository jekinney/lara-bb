<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/healthz', [HealthController::class, 'live']);
Route::get('/readyz', [HealthController::class, 'ready']);

// The installer. InstallGate answers 404 for all of these once laraBB is installed.
Route::prefix('install')->name('install.')->controller(InstallController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/token', 'token')->name('token');
    Route::post('/token', 'submitToken')->middleware('throttle:5,10')->name('token.submit');
    Route::get('/requirements', 'requirements')->name('requirements');
    Route::post('/requirements', 'submitRequirements')->name('requirements.submit');
    Route::get('/database', 'database')->name('database');
    Route::post('/database', 'submitDatabase')->name('database.submit');
    Route::get('/services', 'services')->name('services');
    Route::post('/services', 'submitServices')->name('services.submit');
    Route::get('/site', 'site')->name('site');
    Route::post('/site', 'submitSite')->name('site.submit');
    Route::get('/review', 'review')->name('review');
    Route::post('/run', 'run')->name('run');
    // Answering GET here, rather than "method not allowed", keeps /install/run a 404 after install.
    Route::get('/run', fn () => redirect()->route('install.review'));
});
