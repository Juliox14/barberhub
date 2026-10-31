<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\PasswordPolicy;
use App\Support\TwoFactorAuthentication;
use App\Support\TwoFactorLogin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTwoFactor
{
    public function __construct(private readonly TwoFactorAuthentication $factor, private readonly TwoFactorLogin $login) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('session.status')) {
            return $next($request);
        }
        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }
        $user = $user->fresh();
        if ($user === null || $request->routeIs('logout')) {
            return $next($request);
        }
        if ($this->factor->enabled($user) && ! $this->login->verified($request, $user)) {
            if (! $request->session()->has(TwoFactorLogin::PENDING_KEY)) {
                $this->login->begin($request, $user, authenticated: true);
            }
            if ($request->routeIs('two-factor.challenge', 'two-factor.verify', 'two-factor.cancel')) {
                return $next($request);
            }

            return $request->expectsJson()
                ? $this->restrictedResponse('two_factor_challenge', 'two-factor.challenge')
                : redirect()->route('two-factor.challenge');
        }
        if ($this->login->enrollmentOverdue($user) && ! PasswordPolicy::expired($user)) {
            if ($request->routeIs('two-factor.settings', 'two-factor.begin', 'two-factor.confirm', 'password.renew', 'password.update')) {
                return $next($request);
            }

            return $request->expectsJson()
                ? $this->restrictedResponse('two_factor_required', 'two-factor.settings')
                : redirect()->route('two-factor.settings');
        }

        return $next($request);
    }

    private function restrictedResponse(string $status, string $route): Response
    {
        return response()->json(['status' => $status, 'redirect' => route($route)], 403)
            ->header('Cache-Control', 'no-store, private');
    }
}
