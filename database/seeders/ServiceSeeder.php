<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect([
            ['name' => 'Electrician', 'icon' => 'pi-bolt'],
            ['name' => 'AC Repair', 'icon' => 'pi-sun'],
            ['name' => 'TV Repair', 'icon' => 'pi-desktop'],
            ['name' => 'Fridge Repair', 'icon' => 'pi-box'],
            ['name' => 'Plumber', 'icon' => 'pi-wrench'],
            ['name' => 'Cleaner', 'icon' => 'pi-sparkles'],
            ['name' => 'Carpenter', 'icon' => 'pi-hammer'],
        ])->each(fn (array $service): Service => Service::updateOrCreate(
            ['slug' => Str::slug($service['name'])],
            [
                'name' => $service['name'],
                'description' => "{$service['name']} booking service.",
                'icon' => $service['icon'],
                'is_active' => true,
            ],
        ));
    }
}
