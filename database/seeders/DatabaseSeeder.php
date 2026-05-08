<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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

        User::factory()->for(Role::where('slug', 'admin')->firstOrFail())->create([
            'name' => 'admin',
            'email' => 'admin@gmail.com',
            'password' => 'password',
            'phone' => '9000000001',
        ]);

        User::factory()->for(Role::where('slug', 'customer')->firstOrFail())->create([
            'name' => 'customer',
            'email' => 'customer@gmail.com',
            'password' => 'password',
            'phone' => '9000000002',
        ]);

        User::factory()->for(Role::where('slug', 'worker')->firstOrFail())->create([
            'name' => 'worker',
            'email' => 'worker1@gmail.com',
            'password' => 'password',
            'phone' => '9000000003',
        ]);

        User::factory()->for(Role::where('slug', 'worker')->firstOrFail())->create([
            'name' => 'worker',
            'email' => 'worker2@gmail.com',
            'password' => 'password',
            'phone' => '9000000004',
        ]);
    }
}
