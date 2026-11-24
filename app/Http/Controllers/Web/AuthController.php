<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::lower($data['login']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'login' => 'Demasiadas tentativas. Tenta de novo dentro de '.ceil(RateLimiter::availableIn($key) / 60).' min.',
            ]);
        }

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $ok = Auth::attempt(
            [$field => $data['login'], 'password' => $data['password'], 'can_login' => true, 'activated' => true],
            $request->boolean('remember'),
        );

        if (! $ok) {
            RateLimiter::hit($key, 15 * 60);
            throw ValidationException::withMessages(['login' => 'Login ou password errados.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
