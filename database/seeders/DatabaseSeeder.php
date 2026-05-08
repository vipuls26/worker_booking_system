<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\CustomerProfile;
use App\Models\Review;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestWorker;
use App\Models\User;
use App\Models\WorkerProfile;
use App\Models\WorkerSchedule;
use App\Models\WorkerService;
use App\Models\WorkerVerification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(ServiceSeeder::class);

        $this->cleanupLegacyDemoUsers();

        $admin = $this->seedUser('admin', [
            'name' => 'Admin User',
            'email' => 'admin@gmail.com',
            'phone' => '9000000001',
        ]);

        $this->assignServiceCreators($admin);

        $customers = $this->seedCustomers();
        $workers = $this->seedWorkers($admin);

        $this->resetDemoWorkflows($customers);
        $this->seedCompletedBookings($customers, $workers);
        $this->seedOpenServiceRequests($customers, $workers);
    }

    private function assignServiceCreators(User $admin): void
    {
        Service::query()
            ->whereNull('created_by')
            ->update(['created_by' => $admin->id]);
    }

    private function cleanupLegacyDemoUsers(): void
    {
        User::query()
            ->whereIn('email', [
                'customer@gmail.com',
            ])
            ->delete();
    }

    /**
     * @return Collection<int, User>
     */
    private function seedCustomers(): Collection
    {
        return collect([
            [
                'name' => 'Aarav Shah',
                'email' => 'customer1@gmail.com',
                'phone' => '9100000001',
                'address' => 'A-204, Sunrise Heights, Satellite, Ahmedabad',
            ],
            [
                'name' => 'Nisha Patel',
                'email' => 'customer2@gmail.com',
                'phone' => '9100000002',
                'address' => '12, Green Park Society, Alkapuri, Vadodara',
            ],
            [
                'name' => 'Rahul Mehta',
                'email' => 'customer3@gmail.com',
                'phone' => '9100000003',
                'address' => 'B-17, Royal Residency, Adajan, Surat',
            ],
        ])->map(function (array $customer): User {
            $user = $this->seedUser('customer', $customer);

            CustomerProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['address' => $customer['address']],
            );

            return $user;
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function seedWorkers(User $admin): Collection
    {
        $workers = collect([
            ['name' => 'Vipul Electrician', 'email' => 'worker1@gmail.com', 'phone' => '9200000001', 'city' => 'Ahmedabad', 'experience' => 8, 'services' => ['Electrician', 'AC Repair']],
            ['name' => 'Kiran AC Expert', 'email' => 'worker2@gmail.com', 'phone' => '9200000002', 'city' => 'Ahmedabad', 'experience' => 6, 'services' => ['AC Repair', 'Fridge Repair']],
            ['name' => 'Imran Plumber', 'email' => 'worker3@gmail.com', 'phone' => '9200000003', 'city' => 'Vadodara', 'experience' => 10, 'services' => ['Plumber', 'Cleaner']],
            ['name' => 'Rakesh Carpenter', 'email' => 'worker4@gmail.com', 'phone' => '9200000004', 'city' => 'Surat', 'experience' => 12, 'services' => ['Carpenter', 'TV Repair']],
            ['name' => 'Maya Cleaning Pro', 'email' => 'worker5@gmail.com', 'phone' => '9200000005', 'city' => 'Ahmedabad', 'experience' => 4, 'services' => ['Cleaner']],
            ['name' => 'Dev TV Technician', 'email' => 'worker6@gmail.com', 'phone' => '9200000006', 'city' => 'Rajkot', 'experience' => 7, 'services' => ['TV Repair', 'Electrician']],
            ['name' => 'Sanjay Fridge Care', 'email' => 'worker7@gmail.com', 'phone' => '9200000007', 'city' => 'Vadodara', 'experience' => 9, 'services' => ['Fridge Repair', 'AC Repair']],
            ['name' => 'Hetal Home Cleaner', 'email' => 'worker8@gmail.com', 'phone' => '9200000008', 'city' => 'Surat', 'experience' => 5, 'services' => ['Cleaner', 'Plumber']],
            ['name' => 'Manish Wiring Works', 'email' => 'worker9@gmail.com', 'phone' => '9200000009', 'city' => 'Ahmedabad', 'experience' => 11, 'services' => ['Electrician', 'Carpenter']],
            ['name' => 'Jignesh Repair Hub', 'email' => 'worker10@gmail.com', 'phone' => '9200000010', 'city' => 'Rajkot', 'experience' => 6, 'services' => ['AC Repair', 'TV Repair', 'Fridge Repair']],
        ]);

        return $workers->map(function (array $worker) use ($admin): User {
            $user = $this->seedUser('worker', $worker);

            WorkerProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'profile_photo' => null,
                    'bio' => "{$worker['name']} provides reliable local home service with transparent pricing and clean work.",
                    'experience_years' => $worker['experience'],
                    'address' => "Demo workshop, {$worker['city']}",
                    'city' => $worker['city'],
                    'skills' => $worker['services'],
                    'is_verified' => true,
                ],
            );

            WorkerVerification::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'id_proof' => "worker-proofs/{$user->id}-aadhaar-demo.pdf",
                    'certificates' => ["worker-certificates/{$user->id}-training-demo.pdf"],
                    'experience_years' => $worker['experience'],
                    'mobile_verified' => true,
                    'status' => WorkerVerification::STATUS_APPROVED,
                    'rejection_reason' => null,
                    'verified_by' => $admin->id,
                    'verified_at' => now(),
                ],
            );

            $this->seedWorkerServices($user, $worker['services'], $admin);
            $this->seedWorkerSchedules($user);

            return $user;
        });
    }

    /**
     * @param  array<int, string>  $serviceNames
     */
    private function seedWorkerServices(User $worker, array $serviceNames, User $admin): void
    {
        collect($serviceNames)->each(function (string $serviceName, int $index) use ($worker, $admin): void {
            $service = Service::query()->where('name', $serviceName)->firstOrFail();
            $isHourly = in_array($serviceName, ['Electrician', 'Plumber', 'Cleaner', 'Carpenter'], true);
            $price = $isHourly
                ? 250 + (($worker->id + $index) % 5) * 50
                : 500 + (($worker->id + $index) % 6) * 150;

            WorkerService::query()->updateOrCreate(
                [
                    'worker_id' => $worker->id,
                    'service_id' => $service->id,
                ],
                [
                    'pricing_type' => $isHourly ? WorkerService::PricingHourly : WorkerService::PricingFixed,
                    'price' => $price,
                    'minimum_hours' => $isHourly ? 2 : null,
                    'description' => "Admin approved {$service->name} service by {$worker->name}.",
                    'is_active' => true,
                    'approval_status' => WorkerService::StatusApproved,
                    'rejection_reason' => null,
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => now(),
                ],
            );
        });
    }

    private function seedWorkerSchedules(User $worker): void
    {
        WorkerSchedule::query()->where('worker_id', $worker->id)->delete();

        collect([1, 2, 3, 4, 5, 6])->each(function (int $day) use ($worker): void {
            WorkerSchedule::query()->create([
                'worker_id' => $worker->id,
                'day_of_week' => $day,
                'start_time' => '09:00',
                'end_time' => '13:00',
                'is_off_day' => false,
            ]);

            WorkerSchedule::query()->create([
                'worker_id' => $worker->id,
                'day_of_week' => $day,
                'start_time' => '14:00',
                'end_time' => '18:00',
                'is_off_day' => false,
            ]);
        });

        WorkerSchedule::query()->create([
            'worker_id' => $worker->id,
            'day_of_week' => 0,
            'start_time' => null,
            'end_time' => null,
            'is_off_day' => true,
        ]);
    }

    /**
     * @param  Collection<int, User>  $customers
     */
    private function resetDemoWorkflows(Collection $customers): void
    {
        ServiceRequest::query()
            ->whereIn('customer_id', $customers->pluck('id'))
            ->delete();

        Booking::query()
            ->whereIn('customer_id', $customers->pluck('id'))
            ->delete();
    }

    /**
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, User>  $workers
     */
    private function seedCompletedBookings(Collection $customers, Collection $workers): void
    {
        $serviceNames = ['AC Repair', 'Electrician', 'Cleaner', 'Plumber', 'Fridge Repair'];

        collect($serviceNames)->each(function (string $serviceName, int $index) use ($customers, $workers): void {
            $customer = $customers[$index % $customers->count()];
            $worker = $workers[$index % $workers->count()];
            $workerService = $worker->workerServices()
                ->whereHas('service', fn ($query) => $query->where('name', $serviceName))
                ->first();

            if (! $workerService) {
                return;
            }

            $date = CarbonImmutable::today()->subDays($index + 2);
            $startTime = '10:00';
            $endTime = '12:00';
            $amount = $this->totalAmount($workerService, 120);
            $commission = $this->commissionBreakdown($amount);

            $booking = Booking::query()->create([
                'customer_id' => $customer->id,
                'worker_id' => $worker->id,
                'selected_worker_id' => $worker->id,
                'service_id' => $workerService->service_id,
                'booking_date' => $date->toDateString(),
                'booking_time' => $startTime,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'address' => $customer->customerProfile?->address ?? 'Demo customer address',
                'notes' => "Completed demo {$serviceName} job.",
                'issue_description' => "Demo issue for {$serviceName}.",
                'quoted_amount' => $amount,
                'quoted_commission_rate' => Booking::DefaultCommissionRate,
                'quoted_platform_commission' => $commission['platform_commission'],
                'quoted_worker_earning' => $commission['worker_earning'],
                'status' => Booking::STATUS_COMPLETED,
                'payment_status' => Booking::PAYMENT_PAID,
                'paid_at' => $date->addHours(3),
            ]);

            Review::query()->create([
                'booking_id' => $booking->id,
                'customer_id' => $customer->id,
                'worker_id' => $worker->id,
                'type' => Review::TypeCustomerToWorker,
                'rating' => [5, 4, 5, 4, 5][$index],
                'review' => 'Good service, punctual and professional.',
            ]);
        });
    }

    /**
     * @param  Collection<int, User>  $customers
     * @param  Collection<int, User>  $workers
     */
    private function seedOpenServiceRequests(Collection $customers, Collection $workers): void
    {
        $requestData = [
            ['customer' => 0, 'service' => 'AC Repair', 'date' => 1, 'start' => '15:00', 'end' => '17:00', 'statuses' => [ServiceRequestWorker::STATUS_ACCEPTED, ServiceRequestWorker::STATUS_PENDING, ServiceRequestWorker::STATUS_REJECTED]],
            ['customer' => 1, 'service' => 'Electrician', 'date' => 2, 'start' => '09:00', 'end' => '11:00', 'statuses' => [ServiceRequestWorker::STATUS_PENDING, ServiceRequestWorker::STATUS_PENDING, ServiceRequestWorker::STATUS_ACCEPTED]],
            ['customer' => 2, 'service' => 'Cleaner', 'date' => 3, 'start' => '14:00', 'end' => '16:00', 'statuses' => [ServiceRequestWorker::STATUS_PENDING, ServiceRequestWorker::STATUS_ACCEPTED]],
        ];

        collect($requestData)->each(function (array $data) use ($customers, $workers): void {
            $customer = $customers[$data['customer']];
            $service = Service::query()->where('name', $data['service'])->firstOrFail();
            $matchingServices = WorkerService::query()
                ->where('service_id', $service->id)
                ->where('is_active', true)
                ->where('approval_status', WorkerService::StatusApproved)
                ->whereIn('worker_id', $workers->pluck('id'))
                ->with('worker')
                ->take(count($data['statuses']))
                ->get();

            if ($matchingServices->isEmpty()) {
                return;
            }

            $durationMinutes = CarbonImmutable::parse($data['start'])->diffInMinutes(CarbonImmutable::parse($data['end']));
            $lowestAmount = $matchingServices
                ->map(fn (WorkerService $workerService): float => $this->totalAmount($workerService, $durationMinutes))
                ->min();

            $serviceRequest = ServiceRequest::query()->create([
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'selected_worker_id' => null,
                'booking_id' => null,
                'requested_date' => CarbonImmutable::today()->addDays($data['date'])->toDateString(),
                'start_time' => $data['start'],
                'end_time' => $data['end'],
                'address' => $customer->customerProfile?->address ?? 'Demo customer address',
                'description' => "Demo {$service->name} request for testing worker responses.",
                'estimated_amount' => $lowestAmount,
                'status' => ServiceRequest::STATUS_OPEN,
            ]);

            $matchingServices->values()->each(function (WorkerService $workerService, int $index) use ($serviceRequest, $data, $durationMinutes): void {
                $status = $data['statuses'][$index] ?? ServiceRequestWorker::STATUS_PENDING;

                ServiceRequestWorker::query()->create([
                    'service_request_id' => $serviceRequest->id,
                    'worker_id' => $workerService->worker_id,
                    'worker_service_id' => $workerService->id,
                    'pricing_type' => $workerService->pricing_type,
                    'quoted_price' => $this->totalAmount($workerService, $durationMinutes),
                    'minimum_hours' => $workerService->minimum_hours,
                    'status' => $status,
                    'responded_at' => $status === ServiceRequestWorker::STATUS_PENDING ? null : now(),
                ]);
            });
        });
    }

    /**
     * @param  array{name: string, email: string, phone: string}  $attributes
     */
    private function seedUser(string $roleSlug, array $attributes): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $attributes['email']],
            [
                'role_id' => Role::query()->where('slug', $roleSlug)->firstOrFail()->id,
                'name' => $attributes['name'],
                'phone' => $attributes['phone'],
                'password' => 'password',
                'is_blocked' => false,
                'is_verified' => true,
            ],
        );

        $user->forceFill([
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return $user;
    }

    private function totalAmount(WorkerService $workerService, int $durationMinutes): float
    {
        if ($workerService->pricing_type === WorkerService::PricingHourly) {
            $hours = max($workerService->minimum_hours ?: 1, (int) ceil($durationMinutes / 60));

            return (float) $workerService->price * $hours;
        }

        return (float) $workerService->price;
    }

    /**
     * @return array{platform_commission: float, worker_earning: float}
     */
    private function commissionBreakdown(float $amount): array
    {
        $platformCommission = round($amount * (Booking::DefaultCommissionRate / 100), 2);

        return [
            'platform_commission' => $platformCommission,
            'worker_earning' => round($amount - $platformCommission, 2),
        ];
    }
}
