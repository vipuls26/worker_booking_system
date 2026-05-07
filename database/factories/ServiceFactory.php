<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'icon' => 'pi-briefcase',
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
