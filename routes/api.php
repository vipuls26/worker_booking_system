<?php

use App\Http\Controllers\Api\Account\ProfileController as AccountProfileController;
use App\Http\Controllers\Api\Account\UnblockRequestController;
use App\Http\Controllers\Api\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Api\Admin\CommissionSettingController as AdminCommissionSettingController;
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
use App\Http\Controllers\Api\DisputeController;
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

// List available roles for registration and role-aware UI choices.
Route::get('roles', [RoleController::class, 'index']);

Route::prefix('auth')->group(function (): void {
    // Register a new platform user account.
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');

    // Authenticate a user and issue an API token.
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');

    // Start the password reset flow for a user email.
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth');

    // Complete the password reset flow with a valid token.
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function (): void {
        // Revoke the current authenticated API session.
        Route::post('logout', [AuthController::class, 'logout']);

        // Return the authenticated user's profile and account state.
        Route::get('me', [AuthController::class, 'me']);

        // Update the authenticated user's account profile details.
        Route::put('profile', [AccountProfileController::class, 'update']);

        // Show the authenticated user's latest unblock request.
        Route::get('unblock-request', [UnblockRequestController::class, 'show']);

        // Submit an unblock request for a blocked account.
        Route::post('unblock-request', [UnblockRequestController::class, 'store']);
    });
});

Route::prefix('email')->group(function (): void {
    // Verify a user's email address from a signed email link.
    Route::get('verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Resend the email verification notification for an authenticated user.
    Route::post('verification-notification', [AuthController::class, 'sendVerificationEmail'])
        ->middleware(['auth:sanctum', 'not.blocked', 'throttle:6,1'])
        ->name('verification.send');
});

Route::middleware(['auth:sanctum', 'not.blocked'])->prefix('notifications')->group(function (): void {
    // List notifications for the signed-in user.
    Route::get('/', [NotificationController::class, 'index']);

    // Return the signed-in user's unread notification count.
    Route::get('unread-count', [NotificationController::class, 'unreadCount']);

    // Mark every notification as read for the signed-in user.
    Route::patch('read-all', [NotificationController::class, 'markAllAsRead']);

    // Remove all notifications for the signed-in user.
    Route::delete('clear-all', [NotificationController::class, 'clearAll']);

    // Mark one notification as read for the signed-in user.
    Route::patch('{notification}/read', [NotificationController::class, 'markAsRead']);

    // Delete one notification for the signed-in user.
    Route::delete('{notification}', [NotificationController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'not.blocked', 'verified', 'platform.verified'])->group(function (): void {
    // Let verified platform users list, create, and view their disputes.
    Route::apiResource('disputes', DisputeController::class)
        ->only(['index', 'store', 'show'])
        ->names([
            'index' => 'user-disputes.index',
            'store' => 'user-disputes.store',
            'show' => 'user-disputes.show',
        ]);
});

Route::middleware(['auth:sanctum', 'not.blocked', 'role:admin'])->prefix('admin')->group(function (): void {
    // Show admin dashboard metrics.
    Route::get('dashboard', AdminDashboardController::class);

    // Show admin revenue analytics.
    Route::get('revenue', AdminRevenueController::class);
    Route::get('commission-settings', [AdminCommissionSettingController::class, 'show']);
    Route::patch('commission-settings', [AdminCommissionSettingController::class, 'update']);
    Route::get('audit-logs', AdminAuditLogController::class);

    // List audit log entries for one user.
    Route::get('audit-logs/users/{user}', [AdminAuditLogController::class, 'user']);

    // List audit log entries for one booking.
    Route::get('audit-logs/bookings/{booking}', [AdminAuditLogController::class, 'booking']);

    // Let admins list, view, and update disputes.
    Route::apiResource('disputes', AdminDisputeController::class)->only(['index', 'show', 'update']);

    // Toggle whether a service category is available for booking.
    Route::patch('services/{service}/toggle-status', [AdminServiceController::class, 'toggleStatus']);

    // Let admins manage service categories.
    Route::apiResource('services', AdminServiceController::class);

    // List worker service approval requests.
    Route::get('worker-service-requests', [AdminWorkerServiceApprovalController::class, 'index']);

    // Approve a worker's service offering.
    Route::patch('worker-service-requests/{workerService}/approve', [AdminWorkerServiceApprovalController::class, 'approve']);

    // Reject a worker's service offering.
    Route::patch('worker-service-requests/{workerService}/reject', [AdminWorkerServiceApprovalController::class, 'reject']);

    // List users for admin management.
    Route::get('users', [AdminUserController::class, 'index']);

    // Show one user's admin details.
    Route::get('users/{user}', [AdminUserController::class, 'show']);

    // Block a user from using the platform.
    Route::patch('users/{user}/block', [AdminUserController::class, 'block']);

    // Unblock a user account.
    Route::patch('users/{user}/unblock', [AdminUserController::class, 'unblock']);

    // Mark a user as platform verified.
    Route::patch('users/{user}/verify', [AdminUserController::class, 'verify']);

    // Delete a user account.
    Route::delete('users/{user}', [AdminUserController::class, 'destroy']);

    // List account unblock requests.
    Route::get('unblock-requests', [AdminUnblockRequestController::class, 'index']);

    // Approve an account unblock request.
    Route::patch('unblock-requests/{unblockRequest}/approve', [AdminUnblockRequestController::class, 'approve']);

    // Reject an account unblock request.
    Route::patch('unblock-requests/{unblockRequest}/reject', [AdminUnblockRequestController::class, 'reject']);

    // List worker verification submissions.
    Route::get('worker-verifications', [AdminWorkerVerificationController::class, 'index']);

    // Show one worker verification submission.
    Route::get('worker-verifications/{workerVerification}', [AdminWorkerVerificationController::class, 'show']);

    // Approve a worker verification submission.
    Route::patch('worker-verifications/{workerVerification}/approve', [AdminWorkerVerificationController::class, 'approve']);

    // Reject a worker verification submission.
    Route::patch('worker-verifications/{workerVerification}/reject', [AdminWorkerVerificationController::class, 'reject']);

    // Ask a worker to resubmit verification details.
    Route::patch('worker-verifications/{workerVerification}/request-resubmission', [AdminWorkerVerificationController::class, 'requestResubmission']);

});
Route::middleware(['auth:sanctum', 'not.blocked', 'role:worker'])->prefix('worker')->group(function (): void {
    // Show the worker's public profile details.
    Route::get('profile', [WorkerProfileController::class, 'show']);

    // Update the worker's public profile details.
    Route::post('profile', [WorkerProfileController::class, 'update']);

    // Show the worker's verification status and submission.
    Route::get('verification', [WorkerVerificationController::class, 'show']);

    // Submit worker verification details.
    Route::post('verification', [WorkerVerificationController::class, 'store']);

    Route::middleware(['verified', 'platform.verified'])->group(function (): void {
        // Show worker dashboard metrics.
        Route::get('dashboard', [DashboardController::class, 'worker']);

        // Return worker availability slots for a requested date.
        Route::get('availability', [WorkerScheduleController::class, 'availability']);

        // Show worker earnings information.
        Route::get('earnings', WorkerEarningsController::class);

        // List the worker's weekly schedule windows.
        Route::get('schedules', [WorkerScheduleController::class, 'index']);

        // Create a worker schedule window or off-day.
        Route::post('schedules', [WorkerScheduleController::class, 'store']);

        // Update a worker schedule window or off-day.
        Route::put('schedules/{workerSchedule}', [WorkerScheduleController::class, 'update']);

        // Delete a worker schedule window or off-day.
        Route::delete('schedules/{workerSchedule}', [WorkerScheduleController::class, 'destroy']);

        // List active service categories a worker can offer.
        Route::get('service-options', [WorkerServiceController::class, 'options']);

        // List the worker's service offerings.
        Route::get('services', [WorkerServiceController::class, 'index']);

        // Create a worker service offering.
        Route::post('services', [WorkerServiceController::class, 'store']);

        // Show one worker service offering.
        Route::get('services/{workerService}', [WorkerServiceController::class, 'show']);

        // Update one worker service offering.
        Route::put('services/{workerService}', [WorkerServiceController::class, 'update']);

        // Delete one worker service offering.
        Route::delete('services/{workerService}', [WorkerServiceController::class, 'destroy']);

        // List booking requests sent to the worker.
        Route::get('booking-requests', [WorkerBookingController::class, 'requests']);

        // Show one booking request sent to the worker.
        Route::get('booking-requests/{bookingRequest}', [WorkerBookingController::class, 'showRequest']);

        // Accept, reject, or cancel a booking request.
        Route::patch('booking-requests/{bookingRequest}/respond', [WorkerBookingController::class, 'respond'])->middleware('throttle:booking-actions');

        // List bookings assigned to the worker.
        Route::get('bookings', [WorkerBookingController::class, 'index']);

        // Show one booking assigned to the worker.
        Route::get('bookings/{booking}', [WorkerBookingController::class, 'show']);

        // Move a worker booking through the work status workflow.
        Route::patch('bookings/{booking}/status', [WorkerBookingController::class, 'updateStatus'])->middleware('throttle:booking-actions');

        // Let a worker review the customer after a booking.
        Route::post('bookings/{booking}/review-customer', [ReviewController::class, 'storeForCustomer']);

        // List reviews received by the worker.
        Route::get('reviews', [ReviewController::class, 'myWorkerReviews']);
    });
});
Route::middleware(['auth:sanctum', 'not.blocked', 'role:customer'])->prefix('customer')->group(function (): void {
    // Show customer dashboard metrics.
    Route::get('dashboard', [DashboardController::class, 'customer']);

    Route::middleware(['verified', 'platform.verified'])->group(function (): void {
        // List marketplace filter options for worker search.
        Route::get('worker-search-options', [WorkerSearchController::class, 'options']);

        // Search marketplace-ready workers.
        Route::get('workers', [WorkerSearchController::class, 'index']);

        // Show one marketplace-ready worker and availability.
        Route::get('workers/{worker}', [WorkerSearchController::class, 'show']);

        // List reviews for one worker.
        Route::get('workers/{worker}/reviews', [ReviewController::class, 'workerReviews']);

        // List the customer's booking requests.
        Route::get('bookings', [CustomerBookingController::class, 'index']);

        // Create a new customer booking request.
        Route::post('bookings', [CustomerBookingController::class, 'store'])->middleware('throttle:booking-actions');

        // Show one customer booking request.
        Route::get('bookings/{booking}', [CustomerBookingController::class, 'show']);
<<<<<<< HEAD
        Route::post('bookings/{booking}/book-again', [CustomerBookingController::class, 'bookAgain'])->middleware('throttle:booking-actions');
=======

        // Let a customer review the worker after a booking.
>>>>>>> 8e26619 (fix console file)
        Route::post('bookings/{booking}/review', [ReviewController::class, 'store']);

        // Select the final worker from accepted booking responses.
        Route::patch('bookings/{booking}/select-worker', [CustomerBookingController::class, 'selectWorker'])->middleware('throttle:booking-actions');

        // Pay for a completed customer booking.
        Route::post('bookings/{booking}/pay', [CustomerBookingController::class, 'pay'])->middleware('throttle:booking-actions');

        // Cancel an open customer booking request.
        Route::patch('bookings/{booking}/cancel', [CustomerBookingController::class, 'cancel'])->middleware('throttle:booking-actions');
    });
});
