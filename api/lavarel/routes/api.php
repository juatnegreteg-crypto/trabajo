<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\WifiController;
use App\Http\Controllers\LecturasController;

// Healthcheck
Route::get('/health', [HealthController::class, 'health']);

// Lecturas (ESP32)
Route::post('/lecturas', [LecturasController::class, 'postLecturas']);
// Opcional: listar últimas lecturas (JSON simple)
Route::get('/lecturas', [LecturasController::class, 'getLecturas']);

// WiFi (protegidos opcionalmente por API key)
Route::middleware(['api.key.optional'])->group(function () {
    Route::get('/wifi/get', [WifiController::class, 'get']);      // ?cbi=...
    Route::post('/wifi/put', [WifiController::class, 'put']);     // por CBI
    Route::post('/wifi/put_all', [WifiController::class, 'putAll']); // broadcast "*"
});