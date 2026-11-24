<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class UserAccess
{
    public function assertCanChange(User $target, array $data, User $actor): void
    {
        $role = array_key_exists('role', $data) ? $data['role'] : $target->role;
        $canLogin = array_key_exists('can_login', $data) ? (bool) $data['can_login'] : $target->can_login;
        $active = array_key_exists('activated', $data) ? (bool) $data['activated'] : $target->activated;
        $staysAdmin = $role === User::ROLE_ADMIN && $canLogin && $active;

        if ($target->is($actor) && ! $staysAdmin && $actor->isAdmin()) {
            $this->fail('role', 'Não podes tirar o perfil de admin nem desativar o teu próprio login.');
        }
        if ($target->isAdmin() && $target->activated && ! $staysAdmin && $this->otherActiveAdmins($target) === 0) {
            $this->fail('role', 'Tem de existir pelo menos um admin ativo.');
        }
    }

    public function assertCanDelete(User $target, User $actor): void
    {
        if ($target->is($actor)) {
            $this->fail('id', 'Não te podes apagar a ti próprio.');
        }
        if ($target->isAdmin() && $target->activated && $this->otherActiveAdmins($target) === 0) {
            $this->fail('id', 'Tem de existir pelo menos um admin ativo.');
        }
    }

    public function afterChange(User $user): void
    {
        if (! $user->can_login || ! $user->activated) {
            $user->tokens()->delete();
            $user->forceFill(['remember_token' => null])->saveQuietly();
        }
    }

    private function otherActiveAdmins(User $user): int
    {
        return User::query()->whereKeyNot($user->getKey())
            ->where('role', User::ROLE_ADMIN)->where('can_login', true)->where('activated', true)->count();
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
