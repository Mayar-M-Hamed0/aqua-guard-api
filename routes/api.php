<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WaterSampleController;
use App\Http\Controllers\Api\MonitoringLocationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\WQIAnalysisController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\ReportController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes

// Auth
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/me', [AuthController::class, 'me']);

// Dashboard
Route::prefix('dashboard')->group(function () {
    Route::get('/overview', [DashboardController::class, 'overview']);
    Route::get('/regional', [DashboardController::class, 'regionalStats']);
    Route::get('/parameters/compare', [DashboardController::class, 'compareParameters']);
});

// Monitoring Locations
Route::prefix('locations')->group(function () {
    Route::get('/', [MonitoringLocationController::class, 'index']);
    Route::post('/', [MonitoringLocationController::class, 'store']);
    Route::get('/map', [MonitoringLocationController::class, 'mapView']);
    Route::get('/{id}', [MonitoringLocationController::class, 'show']);
    Route::put('/{id}', [MonitoringLocationController::class, 'update']);
    Route::delete('/{id}', [MonitoringLocationController::class, 'destroy']);
    Route::get('/{id}/statistics', [MonitoringLocationController::class, 'statistics']);
});

// Water Samples
Route::prefix('samples')->group(function () {
    Route::get('/', [WaterSampleController::class, 'index']);
    Route::post('/', [WaterSampleController::class, 'store']);
    Route::get('/map-data', [WaterSampleController::class, 'mapData']);
    Route::post('/import', [WaterSampleController::class, 'import']);
    Route::get('/{id}', [WaterSampleController::class, 'show']);
    Route::put('/{id}', [WaterSampleController::class, 'update']);
    Route::delete('/{id}', [WaterSampleController::class, 'destroy']);
    Route::post('/{id}/verify', [WaterSampleController::class, 'verify']);
});

// WQI Analysis
Route::prefix('analysis')->group(function () {
    Route::post('/calculate', [WQIAnalysisController::class, 'calculate']);
    Route::post('/compare', [WQIAnalysisController::class, 'compareStandards']);
    Route::get('/standards', [WQIAnalysisController::class, 'getStandards']);
    Route::post('/predict', [WQIAnalysisController::class, 'predictFuture']);
});

// Alerts
Route::prefix('alerts')->group(function () {
    Route::get('/', [AlertController::class, 'index']);
    Route::get('/unread', [AlertController::class, 'unread']);
    Route::post('/{id}/mark-read', [AlertController::class, 'markAsRead']);
    Route::post('/{id}/resolve', [AlertController::class, 'resolve']);
    Route::delete('/{id}', [AlertController::class, 'destroy']);
});

// Reports
Route::prefix('reports')->group(function () {
    Route::get('/', [ReportController::class, 'index']);
    Route::get('templates',[ReportController::class, 'templates']);
    Route::post('/generate', [ReportController::class, 'generate']);
    Route::get('/{id}', [ReportController::class, 'show']);
    Route::get('/{id}/download', [ReportController::class, 'download']);
    Route::delete('/{id}', [ReportController::class, 'destroy']);
});


// User Management (Admin only)
Route::middleware('role:admin')->prefix('users')->group(function () {
    Route::get('/', [AuthController::class, 'listUsers']);
    Route::post('/', [AuthController::class, 'createUser']);
    Route::put('/{id}', [AuthController::class, 'updateUser']);
    Route::delete('/{id}', [AuthController::class, 'deleteUser']);
});


// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'version' => '1.0.0',
    ]);
});
