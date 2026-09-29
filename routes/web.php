<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\MemberController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::get('/healthz', [HealthController::class, 'live']);
Route::get('/readyz', [HealthController::class, 'ready']);

// ---- Accounts ----

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
});

Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:10,1'])->name('verification.verify');
Route::post('/email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:3,1')->name('verification.send');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/account', [AccountController::class, 'edit'])->name('account');
    Route::put('/account', [AccountController::class, 'update'])->name('account.update');
    Route::put('/account/password', [AccountController::class, 'password'])->name('account.password');
});

Route::get('/members', [MemberController::class, 'index'])->name('members.index');
Route::get('/members/{username}', [MemberController::class, 'show'])->name('members.show');

// ---- The installer. InstallGate answers 404 for all of these once laraBB is installed. ----

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
