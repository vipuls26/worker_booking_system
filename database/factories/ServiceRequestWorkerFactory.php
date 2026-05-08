<?php

namespace Database\Factories;

use App\Models\ServiceRequest;
use App\Models\ServiceRequestWorker;
use App\Models\User;
use App\Models\WorkerService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequestWorker>
 */
class ServiceRequestWorkerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory(),
            'worker_id' => User::factory(),
            'worker_service_id' => WorkerService::factory(),
            'pricing_type' => WorkerService::PricingFixed,
            'quoted_price' => fake()->numberBetween(300, 1500),
            'minimum_hours' => null,
            'status' => ServiceRequestWorker::STATUS_PENDING,
            'responded_at' => null,
        ];
    }
}
