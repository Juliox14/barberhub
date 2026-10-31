<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\PasswordPolicy;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePasswordCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('session.status')) {
            return $next($request);
        }
        $user = $request->user();

        if (! $user instanceof User || ! PasswordPolicy::expired($user)) {
            return $next($request);
        }

        if ($request->routeIs(
            'two-factor.challenge',
            'two-factor.verify',
            'two-factor.cancel',
            'password.renew',
            'password.update',
            'logout',
            'password.request',
            'password.email',
            'password.reset',
            'password.store',
        )) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return new JsonResponse(['message' => 'Debes renovar tu contraseña para continuar.'], 403);
        }

        return redirect()->route('password.renew');
    }
}
