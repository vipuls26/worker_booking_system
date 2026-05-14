<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingActivity;
use App\Models\CommissionSetting;
use App\Models\CustomerProfile;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestWorker;
use App\Models\UnblockRequest;
use App\Models\User;
use App\Models\WorkerProfile;
use App\Models\WorkerSchedule;
use App\Models\WorkerService;
use App\Models\WorkerVerification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class E2ETestSeeder extends Seeder
{
    /**
     * Seed deterministic records that Playwright can safely reuse across runs.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $adminUser = $this->createUser(
            roleSlug: 'admin',
            name: 'E2E Admin',
            email: 'e2e.admin@example.com',
            phone: '9300000001',
        );

        $customerUser = $this->createUser(
            roleSlug: 'customer',
            name: 'E2E Customer',
            email: 'e2e.customer@example.com',
            phone: '9300000002',
        );

        $customerTwoUser = $this->createUser(
            roleSlug: 'customer',
            name: 'E2E Customer Two',
            email: 'e2e.customer.two@example.com',
            phone: '9300000004',
        );

        $blockedCustomerUser = $this->createUser(
            roleSlug: 'customer',
            name: 'E2E Blocked Customer',
            email: 'e2e.customer.blocked@example.com',
            phone: '9300000005',
            accountStatus: User::STATUS_FULLY_BLOCKED,
            isBlocked: true,
        );

        $partiallyBlockedCustomerUser = $this->createUser(
            roleSlug: 'customer',
            name: 'E2E Partial Customer',
            email: 'e2e.customer.partial@example.com',
            phone: '9300000006',
            accountStatus: User::STATUS_PARTIALLY_BLOCKED,
        );

        $workerUser = $this->createUser(
            roleSlug: 'worker',
            name: 'E2E Worker',
            email: 'e2e.worker@example.com',
            phone: '9300000003',
        );

        $workerTwoUser = $this->createUser(
            roleSlug: 'worker',
            name: 'E2E Worker Two',
            email: 'e2e.worker.two@example.com',
            phone: '9300000007',
        );

        $pendingWorkerUser = $this->createUser(
            roleSlug: 'worker',
            name: 'E2E Pending Worker',
            email: 'e2e.worker.pending@example.com',
            phone: '9300000008',
            isPlatformVerified: false,
        );

        $pendingWorkerTwoUser = $this->createUser(
            roleSlug: 'worker',
            name: 'E2E Pending Worker Two',
            email: 'e2e.worker.pending.two@example.com',
            phone: '9300000009',
            isPlatformVerified: false,
        );

        $this->createCommissionSetting($adminUser);

        $acRepairService = $this->createService(
            slug: 'e2e-ac-repair',
            name: 'E2E AC Repair',
            description: 'Sample AC repair service used by Playwright tests.',
        );

        $plumbingService = $this->createService(
            slug: 'e2e-plumbing',
            name: 'E2E Plumbing',
            description: 'Sample plumbing service used by Playwright tests.',
        );

        $this->createCustomerProfile($customerUser, '123 E2E Street, Ahmedabad');
        $this->createCustomerProfile($customerTwoUser, '456 E2E Avenue, Ahmedabad');
        $this->createCustomerProfile($blockedCustomerUser, '789 E2E Blocked Road, Ahmedabad');
        $this->createCustomerProfile($partiallyBlockedCustomerUser, '321 E2E Partial Road, Ahmedabad');

        $this->createVerifiedWorkerBundle(
            workerUser: $workerUser,
            adminUser: $adminUser,
            service: $acRepairService,
            city: 'Ahmedabad',
            experienceYears: 5,
            price: 500,
        );

        $this->createVerifiedWorkerBundle(
            workerUser: $workerTwoUser,
            adminUser: $adminUser,
            service: $plumbingService,
            city: 'Ahmedabad',
            experienceYears: 4,
            price: 650,
        );

        $this->createApprovedWorkerService(
            workerUser: $workerTwoUser,
            adminUser: $adminUser,
            service: $acRepairService,
            price: 550,
        );

        $this->createPendingWorkerBundle(
            workerUser: $pendingWorkerUser,
            service: $acRepairService,
        );

        $this->createPendingWorkerBundle(
            workerUser: $pendingWorkerTwoUser,
            service: $plumbingService,
        );

        $this->createSeededScenarioBooking(
            customerUser: $customerUser,
            workerUser: $workerUser,
            service: $acRepairService,
            bookingDate: now()->subDays(2)->toDateString(),
            startTime: '10:00',
            endTime: '11:00',
            serviceRequestStatus: ServiceRequest::STATUS_WORKER_SELECTED,
            bookingStatus: Booking::STATUS_COMPLETED,
            paymentStatus: Booking::PAYMENT_UNPAID,
            issueDescription: 'Seeded completed booking for payment flow',
        );

        $this->createSeededScenarioBooking(
            customerUser: $customerTwoUser,
            workerUser: $workerUser,
            service: $acRepairService,
            bookingDate: now()->subDays(3)->toDateString(),
            startTime: '12:00',
            endTime: '13:00',
            serviceRequestStatus: ServiceRequest::STATUS_WORKER_SELECTED,
            bookingStatus: Booking::STATUS_COMPLETED,
            paymentStatus: Booking::PAYMENT_PAID,
            issueDescription: 'Seeded completed booking for review flow',
        );

        $this->createSeededScenarioBooking(
            customerUser: $customerUser,
            workerUser: $workerUser,
            service: $acRepairService,
            bookingDate: now()->subDay()->toDateString(),
            startTime: '14:00',
            endTime: '15:00',
            serviceRequestStatus: ServiceRequest::STATUS_WORKER_SELECTED,
            bookingStatus: Booking::STATUS_CONFIRMED,
            paymentStatus: Booking::PAYMENT_UNPAID,
            issueDescription: 'Seeded confirmed booking for dispute flow',
        );

        $this->createPendingUnblockRequest($blockedCustomerUser);
        $this->createSeedNotification($customerUser, 'Seeded booking update', 'A demo booking update is ready for notification checks.', '/customer/bookings');
        $this->createSeedNotification($workerUser, 'Seeded worker alert', 'A demo worker alert is ready for notification checks.', '/worker/booking-requests');
        $this->createSeedNotification($adminUser, 'Seeded admin alert', 'A demo admin alert is ready for notification checks.', '/admin/dashboard');
    }

    /**
     * Create one user in a known state for role-based E2E login coverage.
     */
    private function createUser(
        string $roleSlug,
        string $name,
        string $email,
        string $phone,
        string $accountStatus = User::STATUS_ACTIVE,
        bool $isBlocked = false,
        bool $isPlatformVerified = true,
    ): User {
        $role = Role::query()
            ->where('slug', $roleSlug)
            ->firstOrFail();

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'role_id' => $role->id,
                'name' => $name,
                'phone' => $phone,
                'password' => bcrypt('password'),
                'account_status' => $accountStatus,
                'is_blocked' => $isBlocked,
                'is_verified' => $isPlatformVerified,
            ],
        );

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        return $user->fresh(['role']);
    }

    /**
     * Keep the global commission setting stable for payment assertions.
     */
    private function createCommissionSetting(User $adminUser): void
    {
        CommissionSetting::query()->updateOrCreate(
            ['name' => CommissionSetting::GlobalSettingName],
            [
                'commission_rate' => 10,
                'updated_by' => $adminUser->id,
            ],
        );
    }

    /**
     * Create one active service that workers can attach to.
     */
    private function createService(string $slug, string $name, string $description): Service
    {
        return Service::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'description' => $description,
                'icon' => 'pi-wrench',
                'is_active' => true,
            ],
        );
    }

    /**
     * Give a customer a stable address so booking tests can reuse profile defaults.
     */
    private function createCustomerProfile(User $customerUser, string $address): void
    {
        CustomerProfile::query()->updateOrCreate(
            ['user_id' => $customerUser->id],
            [
                'address' => $address,
            ],
        );
    }

    /**
     * Create a fully approved worker with profile, verification, services, and weekly hours.
     */
    private function createVerifiedWorkerBundle(
        User $workerUser,
        User $adminUser,
        Service $service,
        string $city,
        int $experienceYears,
        int $price,
    ): void {
        WorkerProfile::query()->updateOrCreate(
            ['user_id' => $workerUser->id],
            [
                'profile_photo' => null,
                'bio' => 'Reliable sample worker for Playwright booking tests.',
                'experience_years' => $experienceYears,
                'address' => 'Demo Workshop, Ahmedabad',
                'city' => $city,
                'skills' => [$service->name],
                'is_verified' => true,
            ],
        );

        WorkerVerification::query()->updateOrCreate(
            ['user_id' => $workerUser->id],
            [
                'id_proof' => 'worker-proofs/'.Str::slug($workerUser->email).'.pdf',
                'certificates' => ['worker-certificates/'.Str::slug($workerUser->email).'.pdf'],
                'experience_years' => $experienceYears,
                'mobile_verified' => true,
                'status' => WorkerVerification::STATUS_APPROVED,
                'verified_by' => $adminUser->id,
                'verified_at' => now(),
            ],
        );

        WorkerService::query()->updateOrCreate(
            [
                'worker_id' => $workerUser->id,
                'service_id' => $service->id,
            ],
            [
                'pricing_type' => WorkerService::PricingFixed,
                'price' => $price,
                'minimum_hours' => null,
                'description' => 'Stable E2E service for Playwright coverage.',
                'is_active' => true,
                'approval_status' => WorkerService::StatusApproved,
                'rejection_reason' => null,
                'reviewed_by' => $adminUser->id,
                'reviewed_at' => now(),
            ],
        );

        $this->createWeeklySchedules($workerUser);
    }

    /**
     * Attach one approved service so a worker can participate in seeded workflow scenarios.
     */
    private function createApprovedWorkerService(User $workerUser, User $adminUser, Service $service, int $price): void
    {
        WorkerService::query()->updateOrCreate(
            [
                'worker_id' => $workerUser->id,
                'service_id' => $service->id,
            ],
            [
                'pricing_type' => WorkerService::PricingFixed,
                'price' => $price,
                'minimum_hours' => null,
                'description' => 'Stable E2E service for Playwright coverage.',
                'is_active' => true,
                'approval_status' => WorkerService::StatusApproved,
                'rejection_reason' => null,
                'reviewed_by' => $adminUser->id,
                'reviewed_at' => now(),
            ],
        );
    }

    /**
     * Create a pending worker record for verification-flow coverage.
     */
    private function createPendingWorkerBundle(User $workerUser, Service $service): void
    {
        WorkerProfile::query()->updateOrCreate(
            ['user_id' => $workerUser->id],
            [
                'profile_photo' => null,
                'bio' => 'Pending worker waiting for admin verification.',
                'experience_years' => 2,
                'address' => 'Pending Workshop, Ahmedabad',
                'city' => 'Ahmedabad',
                'skills' => [$service->name],
                'is_verified' => false,
            ],
        );

        WorkerVerification::query()->updateOrCreate(
            ['user_id' => $workerUser->id],
            [
                'id_proof' => 'worker-proofs/'.Str::slug($workerUser->email).'.pdf',
                'certificates' => ['worker-certificates/'.Str::slug($workerUser->email).'.pdf'],
                'experience_years' => 2,
                'mobile_verified' => true,
                'status' => WorkerVerification::STATUS_PENDING,
                'verified_by' => null,
                'verified_at' => null,
                'rejection_reason' => null,
            ],
        );
    }

    /**
     * Create one working window for each day to keep availability predictable.
     */
    private function createWeeklySchedules(User $workerUser): void
    {
        foreach (range(0, 6) as $dayOfWeek) {
            WorkerSchedule::query()->updateOrCreate(
                [
                    'worker_id' => $workerUser->id,
                    'day_of_week' => $dayOfWeek,
                ],
                [
                    'start_time' => '09:00',
                    'end_time' => '18:00',
                    'is_off_day' => false,
                ],
            );
        }
    }

    /**
     * Seed one pending unblock request so admin unblock flows have a stable record.
     */
    private function createPendingUnblockRequest(User $blockedCustomerUser): void
    {
        UnblockRequest::query()->updateOrCreate(
            [
                'user_id' => $blockedCustomerUser->id,
                'status' => UnblockRequest::STATUS_PENDING,
            ],
            [
                'reason' => 'Please restore access for seeded E2E unblock testing.',
                'admin_note' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ],
        );
    }

    /**
     * Create a reusable seeded booking scenario for payment, review, and dispute tests.
     */
    private function createSeededScenarioBooking(
        User $customerUser,
        User $workerUser,
        Service $service,
        string $bookingDate,
        string $startTime,
        string $endTime,
        string $serviceRequestStatus,
        string $bookingStatus,
        string $paymentStatus,
        string $issueDescription,
    ): void {
        $booking = Booking::query()->updateOrCreate(
            [
                'customer_id' => $customerUser->id,
                'worker_id' => $workerUser->id,
                'service_id' => $service->id,
                'issue_description' => $issueDescription,
            ],
            [
                'selected_worker_id' => $workerUser->id,
                'booking_date' => $bookingDate,
                'booking_time' => $startTime,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'address' => $customerUser->customerProfile?->address,
                'quoted_amount' => 500,
                'quoted_commission_rate' => 10,
                'quoted_platform_commission' => 50,
                'quoted_worker_earning' => 450,
                'status' => $bookingStatus,
                'payment_status' => $paymentStatus,
                'paid_at' => $paymentStatus === Booking::PAYMENT_PAID ? now()->subDay() : null,
            ],
        );

        $serviceRequest = ServiceRequest::query()->updateOrCreate(
            [
                'customer_id' => $customerUser->id,
                'service_id' => $service->id,
                'description' => $issueDescription,
            ],
            [
                'selected_worker_id' => $workerUser->id,
                'booking_id' => $booking->id,
                'requested_date' => $bookingDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'address' => $customerUser->customerProfile?->address,
                'estimated_amount' => 500,
                'status' => $serviceRequestStatus,
            ],
        );

        $booking->update([
            'service_request_id' => $serviceRequest->id,
        ]);

        ServiceRequestWorker::query()->updateOrCreate(
            [
                'service_request_id' => $serviceRequest->id,
                'worker_id' => $workerUser->id,
            ],
            [
                'worker_service_id' => WorkerService::query()
                    ->where('worker_id', $workerUser->id)
                    ->where('service_id', $service->id)
                    ->value('id'),
                'pricing_type' => WorkerService::PricingFixed,
                'quoted_price' => 500,
                'minimum_hours' => null,
                'status' => ServiceRequestWorker::STATUS_SELECTED,
                'response_reason' => null,
                'responded_at' => now()->subDay(),
            ],
        );

        BookingActivity::query()->updateOrCreate(
            [
                'booking_id' => $booking->id,
                'event' => 'seeded_booking_activity',
            ],
            [
                'actor_id' => $workerUser->id,
                'from_status' => $bookingStatus,
                'to_status' => $bookingStatus,
                'note' => 'Seeded booking activity for Playwright detail views.',
            ],
        );
    }

    /**
     * Seed lightweight notifications for dropdown and list UI coverage.
     */
    private function createSeedNotification(User $user, string $title, string $message, string $url): void
    {
        $notificationKey = 'seeded-'.Str::slug($user->email).'-'.Str::slug($title);

        $existingNotification = $user->notifications()
            ->where('type', 'tests.seeded')
            ->get()
            ->first(function ($notification) use ($notificationKey): bool {
                return ($notification->data['seed_key'] ?? null) === $notificationKey;
            });

        if ($existingNotification) {
            return;
        }

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'tests.seeded',
            'data' => [
                'seed_key' => $notificationKey,
                'event' => 'seeded_notification',
                'title' => $title,
                'message' => $message,
                'url' => $url,
            ],
            'read_at' => null,
        ]);
    }
}
