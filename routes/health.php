<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health/app', [HealthController::class, 'app'])->name('health.app');
Route::get('/health/database', [HealthController::class, 'database'])->name('health.database');
Route::get('/health/redis', [HealthController::class, 'redis'])->name('health.redis');
