<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\BillingStatementController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserManagementController;
use App\Http\Controllers\Api\WaterController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  ADD THIS PING ROUTE FOR TESTING
// ============================================================
Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Server is running',
        'timestamp' => now(),
    ]);
});

// ============================================================
//  EXISTING ROUTES
// ============================================================

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/device-activate', [DeviceController::class, 'activate'])->middleware('throttle:6,1');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationEmail']);
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::get('/readings', [WaterController::class, 'index']);
    Route::get('/consumption-history', [WaterController::class, 'history']);
    Route::get('/summary', [ReportController::class, 'summary']);
    Route::get('/live-state', [WaterController::class, 'liveState']);
    Route::patch('/account', [AccountController::class, 'update']);
    Route::patch('/account/password', [AccountController::class, 'password']);

    Route::get('/billing-settings', [BillingController::class, 'index']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/received/all', [NotificationController::class, 'destroyAllReceived']);
    Route::delete('/notifications/received/{notification}', [NotificationController::class, 'destroyReceived']);
    Route::get('/billing-statements', [BillingStatementController::class, 'index']);
    Route::get('/billing-statements/{billingStatement}', [BillingStatementController::class, 'show']);
});

Route::middleware('auth:sanctum')->post('/water-data', [WaterController::class, 'store']);

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/billing-settings', [BillingController::class, 'store']);
    Route::post('/billing-statements/generate', [BillingStatementController::class, 'generate']);
    Route::patch('/billing-statements/{billingStatement}/paid', [BillingStatementController::class, 'markAsPaid']);
    Route::get('/users', [UserManagementController::class, 'index']);
    Route::post('/users', [UserManagementController::class, 'store']);
    Route::get('/users/{user}', [UserManagementController::class, 'show']);
    Route::put('/users/{user}', [UserManagementController::class, 'update']);
    Route::delete('/users/{user}', [UserManagementController::class, 'destroy']);
    Route::get('/reports/consumption', [ReportController::class, 'consumption']);
    Route::get('/reports/notifications', [ReportController::class, 'notifications']);
    Route::get('/reports/leaks', [ReportController::class, 'leaks']);
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::post('/notifications', [NotificationController::class, 'store']);
    Route::get('/municipal-devices', [DeviceController::class, 'index']);
    Route::post('/municipal-devices', [DeviceController::class, 'store']);
    Route::patch('/municipal-devices/{device}', [DeviceController::class, 'update']);
    Route::post('/municipal-devices/{device}/activation-code', [DeviceController::class, 'activationCode']);
    Route::delete('/municipal-devices/{device}', [DeviceController::class, 'decommission']);
    Route::delete('/notifications/sent/all', [NotificationController::class, 'destroyAllSent']);
    Route::delete('/notifications/sent/{notification}', [NotificationController::class, 'destroy']);
    Route::get('/exports/readings', [ExportController::class, 'readings']);
    Route::get('/exports/billing-statements', [ExportController::class, 'billingStatements']);
    Route::get('/exports/notifications', [ExportController::class, 'notifications']);
});
