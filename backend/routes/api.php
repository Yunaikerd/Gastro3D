<?php

use App\Http\Controllers\Api\DishController;
use App\Http\Controllers\Api\EstablishmentMenuController;
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

// Gastro3D - Fase 1 y 2: Generación 3D Estándar y Multivista
Route::prefix('dishes')->group(function () {
    // Balances & Cost Estimator
    Route::get('/balance', [DishController::class, 'balance']);
    Route::post('/cost-estimate', [DishController::class, 'costEstimate']);

    // List & Upload
    Route::get('/', [DishController::class, 'index']);
    Route::post('/upload', [DishController::class, 'upload']);
    Route::post('/multiview', [DishController::class, 'uploadMultiview']);

    // Single Dish & Generation
    Route::get('/{id}', [DishController::class, 'show']);
    Route::post('/{id}/generate', [DishController::class, 'generate']);
    Route::get('/{id}/status', [DishController::class, 'checkStatus']);
    Route::get('/{id}/model', [DishController::class, 'serveModel']);

    // Versiones de Generación 3D (Multivista y Estándar)
    Route::get('/{id}/versions', [DishController::class, 'listVersions']);
    Route::post('/{id}/versions', [DishController::class, 'createVersion']);
    Route::get('/{id}/versions/{versionId}', [DishController::class, 'showVersion']);
    Route::post('/{id}/versions/{versionId}/activate', [DishController::class, 'activateVersion']);
    Route::post('/{id}/versions/{versionId}/generate', [DishController::class, 'generateVersion']);
    Route::get('/{id}/versions/{versionId}/status', [DishController::class, 'checkVersionStatus']);
    Route::get('/{id}/versions/{versionId}/model', [DishController::class, 'serveVersionModel']);
});

// Gastro3D Menu - Etapa 2: API Pública de Menús y Catálogos Multi-Tenant
Route::prefix('v1/establishments')->group(function () {
    Route::get('/{slug}/menu', [EstablishmentMenuController::class, 'menu']);
    Route::get('/{slug}/products/{productSlug}', [EstablishmentMenuController::class, 'productDetail']);
});

