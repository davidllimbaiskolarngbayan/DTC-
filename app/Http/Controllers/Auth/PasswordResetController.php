<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class PasswordResetController extends Controller
{
    public function request()
    {
        return view('auth.forgot');
    }

    public function email(Request $request)
    {
        $request->validate(['email' => ['required', 'email']], [
            'email.required' => 'E-mail Address is required',
            'email.email' => 'Invalid E-mail Address',
        ]);

        try {
            Password::sendResetLink($request->only('email'));
        } catch (Throwable $e) {
            Log::warning('Password reset email failed', ['error' => class_basename($e)]);
        }

        // Same response whether or not the account exists, to avoid revealing registered emails.
        return back()->with('status', 'If that e-mail address is registered, a reset link has been sent. The link will expire after 30 minutes.');
    }

    public function reset(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(12)],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'Invalid or expired reset link. Please request a new one.']);
        }

        return redirect()->route('login')->with('status', 'Password reset successful. Proceed on logging in using your new password');
    }
}
