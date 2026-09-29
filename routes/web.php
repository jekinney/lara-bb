<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/healthz', [HealthController::class, 'live']);
Route::get('/readyz', [HealthController::class, 'ready']);
