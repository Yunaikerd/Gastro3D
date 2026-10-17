<?php

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
