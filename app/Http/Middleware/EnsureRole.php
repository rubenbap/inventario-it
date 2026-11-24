<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->can_login || ! $user->activated || ! in_array($user->role, $roles, true)) {
            throw new AccessDeniedHttpException('Não tens permissão para esta ação.');
        }

        if (! in_array('leitura', $roles, true) && ! $user->tokenAllowsWrite()) {
            throw new AccessDeniedHttpException('Este token é só de leitura.');
        }

        return $next($request);
    }
}
