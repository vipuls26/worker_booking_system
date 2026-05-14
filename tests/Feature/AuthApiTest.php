<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
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
            ->assertJsonPath('data.roles.0.slug', 'customer')
            ->assertJsonPath('data.roles.1.slug', 'worker')
            ->assertJsonMissingPath('data.roles.2')
            ->assertJsonMissing([
                'slug' => 'admin',
            ]);
    }

    public function test_public_registration_rejects_admin_role(): void
    {
        $this->seed(RoleSeeder::class);

        $adminRole = Role::where('slug', 'admin')->firstOrFail();

        $this->postJson('/api/auth/register', [
            'role_id' => $adminRole->id,
            'name' => 'Jane Admin',
            'email' => 'admin-register@example.com',
            'phone' => '9000000011',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['role_id']);
    }

    public function test_registration_uses_custom_validation_messages(): void
    {
        $this->postJson('/api/auth/register', [
            'email' => 'not-an-email',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.role_id.0', 'Please choose an account type.')
            ->assertJsonPath('errors.name.0', 'Please provide name.')
            ->assertJsonPath('errors.email.0', 'Please enter a valid email address.')
            ->assertJsonPath('errors.phone.0', 'Please provide phone number.')
            ->assertJsonPath('errors.password.0', 'Please provide password.');
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

        $issuedToken = User::query()
            ->where('email', 'jane@example.com')
            ->firstOrFail()
            ->tokens()
            ->latest('id')
            ->first();

        $this->assertNotNull($issuedToken);
        $this->assertSame('frontend-spa', $issuedToken->name);
        $this->assertSame(['spa'], $issuedToken->abilities);
        $this->assertNotNull($issuedToken->expires_at);
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

    public function test_api_rejects_personal_access_token_that_was_not_issued_for_the_frontend(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create();

        $token = $user->createToken('server-script')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated');
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

    public function test_login_is_rate_limited_after_too_many_attempts(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => 'missing@example.com',
                'password' => 'password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'password',
        ])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too many attempts. Please try again shortly.');
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

    public function test_authenticated_user_can_request_password_reset_link_for_their_own_email(): void
    {
        Notification::fake();
        $this->seed(RoleSeeder::class);

        $user = User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create(['email' => 'signed-in-customer@example.com']);

        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'signed-in-customer@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password reset link sent');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_fully_blocked_user_can_still_request_email_verification_notification(): void
    {
        Notification::fake();
        $this->seed(RoleSeeder::class);

        $user = User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create([
                'account_status' => User::STATUS_FULLY_BLOCKED,
                'is_blocked' => true,
                'email_verified_at' => null,
            ]);

        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Verification link sent to your email');

        Notification::assertSentTo($user, VerifyEmail::class);
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

    public function test_reset_password_shows_clear_message_when_token_email_pair_does_not_match(): void
    {
        $this->seed(RoleSeeder::class);

        $firstUser = User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create([
                'email' => 'customer1@gmail.com',
                'password' => Hash::make('old-password'),
            ]);

        User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create([
                'email' => 'customer2@gmail.com',
                'password' => Hash::make('old-password'),
            ]);

        $token = Password::broker()->createToken($firstUser);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'customer2@gmail.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unable to reset password')
            ->assertJsonPath('errors.email.0', 'This password reset link does not match that email address or has expired.');
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
