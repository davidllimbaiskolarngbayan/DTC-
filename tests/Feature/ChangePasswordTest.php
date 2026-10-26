<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['password' => Hash::make('old-password-123')]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/password')->assertRedirect('/login');
    }

    public function test_password_is_changed_with_correct_current_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/password', [
            'current_password' => 'old-password-123',
            'password' => 'new-password-4567',
            'password_confirmation' => 'new-password-4567',
        ])->assertSessionHas('status');

        $this->assertTrue(Hash::check('new-password-4567', $user->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/password', [
            'current_password' => 'nope',
            'password' => 'new-password-4567',
            'password_confirmation' => 'new-password-4567',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_short_password_is_rejected(): void
    {
        $this->actingAs($this->user())->post('/password', [
            'current_password' => 'old-password-123',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }
}
