<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_can_be_listed_for_registration(): void
    {
        $this->seed(RoleSeeder::class);

        $this->getJson('/api/roles')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.roles.0.slug', 'admin')
            ->assertJsonPath('data.roles.1.slug', 'customer')
            ->assertJsonPath('data.roles.2.slug', 'worker');
    }

    public function test_user_can_register_and_receive_token(): void
    {
        $this->seed(RoleSeeder::class);

        $customerRole = Role::where('slug', 'customer')->firstOrFail();

        $response = $this->postJson('/api/auth/register', [
            'role_id' => $customerRole->id,
            'name' => 'Jane Customer',
            'email' => 'jane@example.com',
            'phone' => '9000000010',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Registration successful')
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'role_id', 'name', 'email', 'phone', 'role'],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'phone' => '9000000010',
            'role_id' => $customerRole->id,
        ]);
    }

    public function test_user_can_login_get_me_and_logout(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()
            ->for(Role::where('slug', 'worker')->firstOrFail())
            ->create([
                'email' => 'worker@example.com',
                'password' => Hash::make('secret-password'),
            ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'worker@example.com',
            'password' => 'secret-password',
        ]);

        $token = $login->json('data.token');

        $login
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role.slug', 'worker');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout successful');
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid credentials');
    }

    public function test_user_can_request_password_reset_link(): void
    {
        Notification::fake();
        $this->seed(RoleSeeder::class);

        $user = User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create(['email' => 'customer@example.com']);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'customer@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password reset link sent');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()
            ->for(Role::where('slug', 'worker')->firstOrFail())
            ->create([
                'email' => 'reset-worker@example.com',
                'password' => Hash::make('old-password'),
            ]);

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'reset-worker@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password reset successful');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_role_middleware_blocks_other_roles(): void
    {
        $this->seed(RoleSeeder::class);

        Sanctum::actingAs(
            User::factory()->for(Role::where('slug', 'customer')->firstOrFail())->create(),
            ['*'],
        );

        $this->getJson('/api/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_role_middleware_allows_matching_role(): void
    {
        $this->seed(RoleSeeder::class);

        Sanctum::actingAs(
            User::factory()->for(Role::where('slug', 'admin')->firstOrFail())->create(),
            ['*'],
        );

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Admin dashboard');
    }
}
