<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\WorkerVerificationController as AdminWorkerVerificationController;
use App\Http\Controllers\Api\RoleController;
use Illuminate\Support\Facades\Route;

Route::get('roles', [RoleController::class, 'index']);

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function (): void {
    Route::get('dashboard', AdminDashboardController::class);

    Route::patch('services/{service}/toggle-status', [AdminServiceController::class, 'toggleStatus']);
    Route::apiResource('services', AdminServiceController::class);

    Route::get('users', [AdminUserController::class, 'index']);
    Route::get('users/{user}', [AdminUserController::class, 'show']);
    Route::patch('users/{user}/block', [AdminUserController::class, 'block']);
    Route::patch('users/{user}/unblock', [AdminUserController::class, 'unblock']);
    Route::delete('users/{user}', [AdminUserController::class, 'destroy']);

    Route::get('worker-verifications', [AdminWorkerVerificationController::class, 'index']);
    Route::get('worker-verifications/{workerVerification}', [AdminWorkerVerificationController::class, 'show']);
    Route::patch('worker-verifications/{workerVerification}/approve', [AdminWorkerVerificationController::class, 'approve']);
    Route::patch('worker-verifications/{workerVerification}/reject', [AdminWorkerVerificationController::class, 'reject']);

    Route::get('bookings', [AdminBookingController::class, 'index']);
    Route::get('bookings/{booking}', [AdminBookingController::class, 'show']);
    Route::patch('bookings/{booking}/cancel', [AdminBookingController::class, 'cancel']);
});
Route::middleware(['auth:sanctum', 'role:worker'])->get('worker/dashboard', [DashboardController::class, 'worker']);
Route::middleware(['auth:sanctum', 'role:customer'])->get('customer/dashboard', [DashboardController::class, 'customer']);
