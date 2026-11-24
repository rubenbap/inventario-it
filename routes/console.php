<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Password;

Artisan::command('inventario:admin {email} {--name=Admin}', function (string $email) {
    $password = $this->secret('Password (mínimo 10 caracteres)');
    if (mb_strlen((string) $password) < 10) {
        $this->error('A password tem de ter pelo menos 10 caracteres.');

        return 1;
    }

    $user = User::withTrashed()->firstOrNew(['email' => $email]);
    $user->fill([
        'first_name' => $user->first_name ?: $this->option('name'),
        'password' => $password,
        'can_login' => true,
        'role' => User::ROLE_ADMIN,
        'activated' => true,
    ]);
    $user->deleted_at = null;
    $user->save();

    $this->info(($user->wasRecentlyCreated ? 'Admin criado: ' : 'Admin atualizado: ').$email);

    return 0;
})->purpose('Cria um login de administrador ou repõe a password de um existente');

Artisan::command('inventario:link-password {email}', function (string $email) {
    $user = User::where('email', $email)->where('can_login', true)->first();
    if (! $user) {
        $this->error('Não há nenhum login com esse e-mail.');

        return 1;
    }
    $token = Password::broker()->createToken($user);
    $this->line(url(route('password.reset', ['token' => $token, 'email' => $user->email], false)));
    $this->info('Link válido durante '.config('auth.passwords.users.expire', 60).' minutos. Envia-o à pessoa.');

    return 0;
})->purpose('Gera um link para a pessoa definir uma nova password, sem precisar de e-mail configurado');
