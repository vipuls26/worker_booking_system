<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect([
            ['name' => 'Admin', 'slug' => 'admin'],
            ['name' => 'Customer', 'slug' => 'customer'],
            ['name' => 'Worker', 'slug' => 'worker'],
        ])->each(fn (array $role): Role => Role::updateOrCreate(
            ['slug' => $role['slug']],
            ['name' => $role['name']],
        ));
    }
}
