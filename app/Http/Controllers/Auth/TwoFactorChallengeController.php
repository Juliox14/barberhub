<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccountLoginGuard;
use App\Support\CustomerInvitationService;
use App\Support\SecurityAuditLogger;
use App\Support\SingleDeviceSession;
use App\Support\TenantDashboard;
use App\Support\TwoFactorAuthentication;
use App\Support\TwoFactorLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final class TwoFactorChallengeController extends Controller
{
    public function show(Request $request, TwoFactorLogin $login): Response
    {
        $login->pendingUser($request);

        return response()->view('auth.two-factor-challenge')->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, TwoFactorLogin $login, TwoFactorAuthentication $factor): Response
    {
        $user = $login->pendingUser($request);
        $key = 'two-factor-login:'.$user->getKey();
        abort_if(RateLimiter::tooManyAttempts($key, 6), 429);
        $code = $request->input('code');
        $recovery = $request->input('recovery_code');
        if ((! is_string($code) && ! is_string($recovery)) || (is_string($code) && is_string($recovery))) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['code' => 'Ingresa un código de autenticación o de recuperación válido.']);
        }

        return DB::transaction(function () use ($request, $login, $factor, $user, $key, $code, $recovery): Response {
            User::query()->lockForUpdate()->findOrFail($user->getKey());
            $user = $login->pendingUser($request);
            abort_if(app(AccountLoginGuard::class)->isLocked($user), 423);
            try {
                $valid = $factor->verify($user, is_string($code) ? $code : null, is_string($recovery) ? $recovery : null);
            } catch (\DomainException) {
                abort(419);
            }
            if (! $valid) {
                RateLimiter::hit($key, 60);
                app(SecurityAuditLogger::class)->record('two_factor.failed', subject: $user, request: $request);

                return back()->withErrors(['code' => 'El código de autenticación o recuperación no es válido.']);
            }
            RateLimiter::clear($key);
            app(SecurityAuditLogger::class)->record('two_factor.verified', $user, $user, request: $request);
            $pending = $request->session()->get(TwoFactorLogin::PENDING_KEY);
            $user->refresh();
            $login->markVerified($request, $user);
            if ($pending['authenticated']) {
                $destination = TenantDashboard::destinationFor($user);

                return redirect()->route($destination['route'] ?? 'profile.edit', $destination['parameters'] ?? []);
            }

            $response = app(AuthenticatedSessionController::class)->finishAuthentication(
                $request, $user, (bool) $pending['remember'],
                app(CustomerInvitationService::class), app(SingleDeviceSession::class),
                app(AccountLoginGuard::class),
            );

            return $response instanceof Response ? $response : response($response);
        });
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget([TwoFactorLogin::PENDING_KEY, SingleDeviceSession::PENDING_KEY]);

        return redirect()->route('login');
    }
}
