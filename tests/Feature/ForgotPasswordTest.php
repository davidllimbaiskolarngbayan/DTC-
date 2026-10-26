<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\DtcResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_has_forgot_password_link(): void
    {
        $this->get('/login')->assertOk()->assertSee('Forgot Your Password?');
    }

    public function test_reset_link_is_sent_to_known_email_and_response_is_generic(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->post('/forgot-password', ['email' => $user->email]);
        $unknown = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

        Notification::assertSentTo($user, DtcResetPasswordNotification::class, function (DtcResetPasswordNotification $notification) use ($user) {
            $mail = $notification->toMail($user);
            $this->assertSame('Reset your password', $mail->subject);
            $this->assertSame('emails.auth.password-reset', $mail->view);
            $this->assertSame($user->name, $mail->viewData['userName']);
            $this->assertStringContainsString('/password/reset/', $mail->viewData['url']);

            return true;
        });
        $known->assertSessionHas('status');
        $unknown->assertSessionHas('status');
        $this->assertSame($known->getSession()->get('status'), $unknown->getSession()->get('status'));
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post('/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'brand-new-password-1',
            'password_confirmation' => 'brand-new-password-1',
        ])->assertRedirect('/login');

        $this->assertTrue(Hash::check('brand-new-password-1', $user->fresh()->password));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post('/password/reset', [
            'token' => 'bad-token',
            'email' => $user->email,
            'password' => 'brand-new-password-1',
            'password_confirmation' => 'brand-new-password-1',
        ])->assertSessionHasErrors('email');
    }
}
