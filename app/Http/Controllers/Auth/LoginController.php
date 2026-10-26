<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'min:3'],
        ], [
            'email.required' => 'E-mail Address is required',
            'email.email' => 'Invalid E-mail Address',
            'password.required' => 'Password is required',
        ]);

        $key = Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['loginDetails' => 'Too many attempts. Try again later.'])->onlyInput('email');
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['loginDetails' => 'Wrong Login Details'])->onlyInput('email');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
