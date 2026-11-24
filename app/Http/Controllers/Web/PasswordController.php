<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public const SENT = 'Se o e-mail tiver login ativo, vais receber um link para definir uma nova password. Vê também o spam.';

    public function request(): View
    {
        return view('auth.forgot');
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:191']]);
        $user = User::whereRaw('lower(email) = ?', [Str::lower($data['email'])])->where('can_login', true)->where('activated', true)->first();
        if ($user) {
            Password::broker()->sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', self::SENT);
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rule::min(10)],
        ]);

        $status = Password::broker()->reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            ActivityLogger::log('update', $user, null, 'Password reposta pelo próprio (link por e-mail)');
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'O link é inválido ou já expirou. Pede um novo.']);
        }

        return redirect()->route('login')->with('status', 'Password alterada. Já podes entrar.');
    }
}
