<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\BillingStatementController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserManagementController;
use App\Http\Controllers\Api\WaterController;

// ============================================================
//  ADD THIS PING ROUTE FOR TESTING
// ============================================================
Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Server is running',
        'timestamp' => now()
    ]);
});

// ============================================================
//  EXISTING ROUTES
// ============================================================

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/device-token', [AuthController::class, 'deviceToken']);
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
    Route::get('/summary', [ReportController::class, 'summary']);
    Route::get('/live-state', [WaterController::class, 'liveState']);

    Route::get('/billing-settings', [BillingController::class, 'index']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::get('/billing-statements', [BillingStatementController::class, 'index']);
    Route::get('/billing-statements/{billingStatement}', [BillingStatementController::class, 'show']);
});

Route::middleware('auth:sanctum')->post('/water-data', [WaterController::class, 'store']);

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/billing-settings', [BillingController::class, 'store']);
    Route::post('/billing-statements/generate', [BillingStatementController::class, 'generate']);
    Route::patch('/billing-statements/{billingStatement}/paid', [BillingStatementController::class, 'markAsPaid']);
    Route::get('/devices/{user}/tokens', [AuthController::class, 'listDeviceTokens']);
    Route::post('/devices/{user}/tokens', [AuthController::class, 'issueDeviceToken']);
    Route::delete('/devices/{user}/tokens/{tokenId}', [AuthController::class, 'revokeDeviceToken']);
    Route::get('/users', [UserManagementController::class, 'index']);
    Route::post('/users', [UserManagementController::class, 'store']);
    Route::get('/users/{user}', [UserManagementController::class, 'show']);
    Route::put('/users/{user}', [UserManagementController::class, 'update']);
    Route::delete('/users/{user}', [UserManagementController::class, 'destroy']);
    Route::get('/reports/consumption', [ReportController::class, 'consumption']);
    Route::get('/reports/notifications', [ReportController::class, 'notifications']);
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::get('/exports/readings', [ExportController::class, 'readings']);
    Route::get('/exports/billing-statements', [ExportController::class, 'billingStatements']);
    Route::get('/exports/notifications', [ExportController::class, 'notifications']);
});
