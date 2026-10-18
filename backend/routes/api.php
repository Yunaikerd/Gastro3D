<?php

use App\Http\Controllers\Api\DishController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/health', function () {
    $dbStatus = 'disconnected';
    $dbError = null;

    try {
        DB::connection()->getPdo();
        $dbStatus = 'connected';
    } catch (\Throwable $e) {
        $dbError = $e->getMessage();
    }

    $isHealthy = $dbStatus === 'connected';

    return response()->json([
        'status' => $isHealthy ? 'healthy' : 'unhealthy',
        'service' => 'Gastro3D Backend API',
        'timestamp' => now()->toIso8601String(),
        'database' => [
            'status' => $dbStatus,
            'driver' => config('database.default'),
            'error' => $dbError,
        ],
    ], $isHealthy ? 200 : 503);
});

// Tripo AI V3 Account & Balance
Route::get('/tripo/balance', [DishController::class, 'balance']);

// Gastro3D - Fase 1: Generación 3D de Comida con Tripo AI V3
Route::prefix('dishes')->group(function () {
    Route::get('/balance', [DishController::class, 'balance']);
    Route::get('/', [DishController::class, 'index']);
    Route::post('/upload', [DishController::class, 'upload']);
    Route::get('/{id}', [DishController::class, 'show']);
    Route::post('/{id}/generate', [DishController::class, 'generate']);
    Route::get('/{id}/status', [DishController::class, 'checkStatus']);
    Route::get('/{id}/model', [DishController::class, 'serveModel']);
});
