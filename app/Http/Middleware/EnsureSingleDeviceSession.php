<?php

namespace App\Http\Middleware;

use App\Support\SingleDeviceSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSingleDeviceSession
{
    public function __construct(private SingleDeviceSession $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        $user = $request->user();

        if ($user === null || ! $request->session()->has($guard->getName())) {
            return $next($request);
        }

        $ownerToken = $request->session()->get(SingleDeviceSession::OWNER_TOKEN_KEY);
        $ownsSession = false;

        if (! is_string($ownerToken) && Auth::guard('web')->viaRemember()) {
            $ownerToken = $this->sessions->restoreRememberedSession($user);

            if ($ownerToken !== null) {
                $request->session()->put(SingleDeviceSession::OWNER_TOKEN_KEY, $ownerToken);
            }
        } elseif (! is_string($ownerToken)) {
            $ownerToken = Str::random(64);
            $request->session()->put(SingleDeviceSession::OWNER_TOKEN_KEY, $ownerToken);

            $ownsSession = $this->sessions->claimLegacySession($user, $ownerToken);
        }

        if (is_string($ownerToken)) {
            $ownsSession = $this->sessions->touch($user, $ownerToken) || ($ownsSession ?? false);
        }

        if (! $ownsSession) {
            Auth::guard('web')->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
