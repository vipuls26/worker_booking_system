<?php

use App\Http\Controllers\Api\Account\ProfileController as AccountProfileController;
use App\Http\Controllers\Api\Account\UnblockRequestController;
use App\Http\Controllers\Api\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\DisputeController as AdminDisputeController;
use App\Http\Controllers\Api\Admin\RevenueController as AdminRevenueController;
use App\Http\Controllers\Api\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Api\Admin\UnblockRequestController as AdminUnblockRequestController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\WorkerServiceApprovalController as AdminWorkerServiceApprovalController;
use App\Http\Controllers\Api\Admin\WorkerVerificationController as AdminWorkerVerificationController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Customer\BookingController as CustomerBookingController;
use App\Http\Controllers\Api\Customer\WorkerSearchController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\Worker\BookingController as WorkerBookingController;
use App\Http\Controllers\Api\Worker\EarningsController as WorkerEarningsController;
use App\Http\Controllers\Api\Worker\ProfileController as WorkerProfileController;
use App\Http\Controllers\Api\Worker\ScheduleController as WorkerScheduleController;
use App\Http\Controllers\Api\Worker\ServiceController as WorkerServiceController;
use App\Http\Controllers\Api\Worker\VerificationController as WorkerVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('roles', [RoleController::class, 'index']);

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::put('profile', [AccountProfileController::class, 'update']);
        Route::get('unblock-request', [UnblockRequestController::class, 'show']);
        Route::post('unblock-request', [UnblockRequestController::class, 'store']);
    });
});

Route::prefix('email')->group(function (): void {
    Route::get('verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('verification-notification', [AuthController::class, 'sendVerificationEmail'])
        ->middleware(['auth:sanctum', 'not.blocked', 'throttle:6,1'])
        ->name('verification.send');
});

Route::middleware(['auth:sanctum', 'not.blocked'])->prefix('notifications')->group(function (): void {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('clear-all', [NotificationController::class, 'clearAll']);
    Route::patch('{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('{notification}', [NotificationController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'not.blocked', 'role:admin'])->prefix('admin')->group(function (): void {
    Route::get('dashboard', AdminDashboardController::class);
    Route::get('revenue', AdminRevenueController::class);
    Route::get('audit-logs', AdminAuditLogController::class);
    Route::get('audit-logs/users/{user}', [AdminAuditLogController::class, 'user']);
    Route::get('audit-logs/bookings/{booking}', [AdminAuditLogController::class, 'booking']);
    Route::apiResource('disputes', AdminDisputeController::class)->only(['index', 'show', 'update']);

    Route::patch('services/{service}/toggle-status', [AdminServiceController::class, 'toggleStatus']);
    Route::apiResource('services', AdminServiceController::class);

    Route::get('worker-service-requests', [AdminWorkerServiceApprovalController::class, 'index']);
    Route::patch('worker-service-requests/{workerService}/approve', [AdminWorkerServiceApprovalController::class, 'approve']);
    Route::patch('worker-service-requests/{workerService}/reject', [AdminWorkerServiceApprovalController::class, 'reject']);

    Route::get('users', [AdminUserController::class, 'index']);
    Route::get('users/{user}', [AdminUserController::class, 'show']);
    Route::patch('users/{user}/block', [AdminUserController::class, 'block']);
    Route::patch('users/{user}/unblock', [AdminUserController::class, 'unblock']);
    Route::patch('users/{user}/verify', [AdminUserController::class, 'verify']);
    Route::delete('users/{user}', [AdminUserController::class, 'destroy']);

    Route::get('unblock-requests', [AdminUnblockRequestController::class, 'index']);
    Route::patch('unblock-requests/{unblockRequest}/approve', [AdminUnblockRequestController::class, 'approve']);
    Route::patch('unblock-requests/{unblockRequest}/reject', [AdminUnblockRequestController::class, 'reject']);

    Route::get('worker-verifications', [AdminWorkerVerificationController::class, 'index']);
    Route::get('worker-verifications/{workerVerification}', [AdminWorkerVerificationController::class, 'show']);
    Route::patch('worker-verifications/{workerVerification}/approve', [AdminWorkerVerificationController::class, 'approve']);
    Route::patch('worker-verifications/{workerVerification}/reject', [AdminWorkerVerificationController::class, 'reject']);
    Route::patch('worker-verifications/{workerVerification}/request-resubmission', [AdminWorkerVerificationController::class, 'requestResubmission']);

});
Route::middleware(['auth:sanctum', 'not.blocked', 'role:worker'])->prefix('worker')->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'worker']);
    Route::get('profile', [WorkerProfileController::class, 'show']);
    Route::post('profile', [WorkerProfileController::class, 'update']);
    Route::get('verification', [WorkerVerificationController::class, 'show']);
    Route::post('verification', [WorkerVerificationController::class, 'store']);
    Route::middleware(['verified', 'platform.verified'])->group(function (): void {
        Route::get('availability', [WorkerScheduleController::class, 'availability']);
        Route::get('earnings', WorkerEarningsController::class);
        Route::get('schedules', [WorkerScheduleController::class, 'index']);
        Route::post('schedules', [WorkerScheduleController::class, 'store']);
        Route::put('schedules/{workerSchedule}', [WorkerScheduleController::class, 'update']);
        Route::delete('schedules/{workerSchedule}', [WorkerScheduleController::class, 'destroy']);
        Route::get('service-options', [WorkerServiceController::class, 'options']);
        Route::get('services', [WorkerServiceController::class, 'index']);
        Route::post('services', [WorkerServiceController::class, 'store']);
        Route::get('services/{workerService}', [WorkerServiceController::class, 'show']);
        Route::put('services/{workerService}', [WorkerServiceController::class, 'update']);
        Route::delete('services/{workerService}', [WorkerServiceController::class, 'destroy']);
        Route::get('booking-requests', [WorkerBookingController::class, 'requests']);
        Route::get('booking-requests/{bookingRequest}', [WorkerBookingController::class, 'showRequest']);
        Route::patch('booking-requests/{bookingRequest}/respond', [WorkerBookingController::class, 'respond'])->middleware('throttle:booking-actions');
        Route::get('bookings', [WorkerBookingController::class, 'index']);
        Route::get('bookings/{booking}', [WorkerBookingController::class, 'show']);
        Route::patch('bookings/{booking}/status', [WorkerBookingController::class, 'updateStatus'])->middleware('throttle:booking-actions');
        Route::post('bookings/{booking}/review-customer', [ReviewController::class, 'storeForCustomer']);
        Route::get('reviews', [ReviewController::class, 'myWorkerReviews']);
    });
});
Route::middleware(['auth:sanctum', 'not.blocked', 'role:customer'])->prefix('customer')->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'customer']);
    Route::middleware(['verified', 'platform.verified'])->group(function (): void {
        Route::get('worker-search-options', [WorkerSearchController::class, 'options']);
        Route::get('workers', [WorkerSearchController::class, 'index']);
        Route::get('workers/{worker}', [WorkerSearchController::class, 'show']);
        Route::get('workers/{worker}/reviews', [ReviewController::class, 'workerReviews']);
        Route::get('bookings', [CustomerBookingController::class, 'index']);
        Route::post('bookings', [CustomerBookingController::class, 'store'])->middleware('throttle:booking-actions');
        Route::get('bookings/{booking}', [CustomerBookingController::class, 'show']);
        Route::post('bookings/{booking}/review', [ReviewController::class, 'store']);
        Route::patch('bookings/{booking}/select-worker', [CustomerBookingController::class, 'selectWorker'])->middleware('throttle:booking-actions');
        Route::post('bookings/{booking}/pay', [CustomerBookingController::class, 'pay'])->middleware('throttle:booking-actions');
        Route::patch('bookings/{booking}/cancel', [CustomerBookingController::class, 'cancel'])->middleware('throttle:booking-actions');
    });
});
