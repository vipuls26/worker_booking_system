<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestWorker;
use App\Models\User;
use App\Models\WorkerSchedule;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_returns_chart_ready_report_data(): void
    {
        [$admin, $worker, $customer, $service] = $this->actors();

        Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_COMPLETED,
            'quoted_amount' => 1200,
            'quoted_platform_commission' => 120,
            'quoted_worker_earning' => 1080,
        ])->payments()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'amount' => 1200,
            'commission_rate' => 10,
            'platform_commission' => 120,
            'worker_earning' => 1080,
            'provider' => 'manual',
            'transaction_reference' => 'REPORT-001',
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'cards',
                    'revenue_reports' => ['monthly', 'by_status'],
                    'booking_statuses',
                    'popular_services',
                ],
            ])
            ->assertJsonPath('data.total_revenue', 120);
    }

    public function test_worker_dashboard_returns_earnings_ratings_and_top_services(): void
    {
        [, $worker, $customer, $service] = $this->actors();

        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_COMPLETED,
            'quoted_amount' => 900,
            'quoted_platform_commission' => 90,
            'quoted_worker_earning' => 810,
        ]);
        Payment::factory()->create([
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'amount' => 900,
            'commission_rate' => 10,
            'platform_commission' => 90,
            'worker_earning' => 810,
            'status' => Payment::STATUS_PAID,
        ]);

        Review::factory()->create([
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'rating' => 5,
        ]);

        WorkerSchedule::factory()->create([
            'worker_id' => $worker->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_off_day' => false,
        ]);

        $serviceRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'requested_date' => now()->addDay()->toDateString(),
            'start_time' => '13:00',
            'end_time' => '15:00',
        ]);

        ServiceRequestWorker::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'worker_id' => $worker->id,
            'status' => ServiceRequestWorker::STATUS_PENDING,
        ]);

        Sanctum::actingAs($worker);

        $this->getJson('/api/worker/dashboard')
            ->assertOk()
            ->assertJsonPath('data.analytics.earnings', 810)
            ->assertJsonPath('data.analytics.completed_bookings', 1)
            ->assertJsonPath('data.analytics.average_rating', 5.0)
            ->assertJsonPath('data.analytics.pending_request_count', 1)
            ->assertJsonPath('data.analytics.upcoming_bookings.0.id', $booking->id)
            ->assertJsonStructure([
                'data' => [
                    'analytics' => [
                        'cards',
                        'earnings_chart',
                        'booking_statuses',
                        'top_services',
                        'recent_reviews',
                        'availability',
                        'pending_request_count',
                        'upcoming_bookings',
                    ],
                ],
            ]);
    }

    /**
     * @return array{User, User, User, Service}
     */
    private function actors(): array
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->for(Role::where('slug', 'admin')->firstOrFail())->create();
        $worker = User::factory()->for(Role::where('slug', 'worker')->firstOrFail())->create();
        $customer = User::factory()->for(Role::where('slug', 'customer')->firstOrFail())->create();
        $service = Service::factory()->create(['is_active' => true]);

        return [$admin, $worker, $customer, $service];
    }
}
