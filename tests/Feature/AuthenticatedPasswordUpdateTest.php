<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticatedPasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure an authenticated user can change their password from dashboard settings.
     */
    public function test_authenticated_user_can_update_password(): void
    {
        $user = User::factory()->for(Role::factory())->create([
            'password' => Hash::make('old-password'),
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->putJson('/api/auth/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password updated successfully');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    /**
     * Ensure the password update rejects an incorrect current password.
     */
    public function test_authenticated_user_must_provide_correct_current_password(): void
    {
        $user = User::factory()->for(Role::factory())->create([
            'password' => Hash::make('old-password'),
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->putJson('/api/auth/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.current_password.0', 'The current password is incorrect.');
    }

    /**
     * Ensure the new password must differ from the current password.
     */
    public function test_authenticated_user_must_choose_a_different_new_password(): void
    {
        $user = User::factory()->for(Role::factory())->create([
            'password' => Hash::make('same-password'),
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->putJson('/api/auth/password', [
            'current_password' => 'same-password',
            'password' => 'same-password',
            'password_confirmation' => 'same-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.password.0', 'Please choose a new password that is different from your current password.');
    }
}
