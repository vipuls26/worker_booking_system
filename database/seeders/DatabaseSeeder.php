<?php

namespace Database\Seeders;

use App\Models\CustomerProfile;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkerProfile;
use App\Models\WorkerVerification;
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

        // Remove old demo users
        $this->cleanupLegacyDemoUsers();

        $admin = $this->seedUser('admin', [
            'name' => 'Admin User',
            'email' => 'admin@gmail.com',
            'phone' => '9000000001',
        ]);

        $this->assignServiceCreators($admin);

        $this->seedCustomers();

        $this->seedWorkers($admin);
    }

    private function assignServiceCreators(User $admin): void
    {
        Service::query()
            ->whereNull('created_by')
            ->update([
                'created_by' => $admin->id,
            ]);
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
                'address' => 'Ahmedabad',
            ],
            [
                'name' => 'Nisha Patel',
                'email' => 'customer2@gmail.com',
                'phone' => '9100000002',
                'address' => 'Vadodara',
            ],
            [
                'name' => 'Rahul Mehta',
                'email' => 'customer3@gmail.com',
                'phone' => '9100000003',
                'address' => 'Surat',
            ],
        ])->map(function (array $customer): User {

            $user = $this->seedUser('customer', $customer);

            CustomerProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'address' => $customer['address'],
                ],
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
            [
                'name' => 'Vipul Electrician',
                'email' => 'worker1@gmail.com',
                'phone' => '9200000001',
                'city' => 'Ahmedabad',
                'experience' => 8,
                'services' => ['Electrician', 'AC Repair'],
            ],
            [
                'name' => 'Kiran AC Expert',
                'email' => 'worker2@gmail.com',
                'phone' => '9200000002',
                'city' => 'Ahmedabad',
                'experience' => 6,
                'services' => ['AC Repair', 'Fridge Repair'],
            ],
        ]);

        return $workers->map(function (array $worker) use ($admin): User {

            $user = $this->seedUser('worker', $worker);

            WorkerProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'profile_photo' => null,
                    'bio' => "{$worker['name']} provides reliable local home service.",
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
                    'id_proof' => "worker-proofs/{$user->id}.pdf",
                    'certificates' => [
                        "worker-certificates/{$user->id}.pdf",
                    ],
                    'experience_years' => $worker['experience'],
                    'mobile_verified' => true,
                    'status' => WorkerVerification::STATUS_APPROVED,
                    'verified_by' => $admin->id,
                    'verified_at' => now(),
                ],
            );

            return $user;
        });
    }

    /**
     * @param  array{name:string,email:string,phone:string}  $attributes
     */
    private function seedUser(string $roleSlug, array $attributes): User
    {
        $role = Role::query()
            ->where('slug', $roleSlug)
            ->firstOrFail();

        $user = User::query()->updateOrCreate(
            [
                'email' => $attributes['email'],
            ],
            [
                'role_id' => $role->id,
                'name' => $attributes['name'],
                'phone' => $attributes['phone'],
                'password' => bcrypt('password'),
                'is_blocked' => false,
                'is_verified' => true,
            ],
        );

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }
}
