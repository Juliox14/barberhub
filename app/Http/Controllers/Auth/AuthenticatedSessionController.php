<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Barbershop;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\AccountLoginGuard;
use App\Support\CustomerInvitationService;
use App\Support\SecurityAuditLogger;
use App\Support\SingleDeviceSession;
use App\Support\TenantDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): View
    {
        return view('auth.login', [
            'sessionNotice' => in_array($request->query('session'), ['revoked', 'expired'], true)
                ? $request->query('session')
                : null,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, CustomerInvitationService $invitations, SingleDeviceSession $sessions, SecurityAuditLogger $auditLogger, AccountLoginGuard $loginGuard): RedirectResponse|View
    {
        $request->session()->forget(SingleDeviceSession::PENDING_KEY);
        $user = $request->authenticate($sessions, $auditLogger, $loginGuard);
        $ownerToken = Str::random(64);

        $expectedVersion = (int) $user->getAttribute('security_session_version');

        if (! $loginGuard->claimAfterCredentials($user, $ownerToken, $expectedVersion, $sessions)) {
            $request->session()->put(SingleDeviceSession::PENDING_KEY, [
                'user_id' => $user->getKey(),
                'remember' => $request->boolean('remember'),
                'session_id' => $request->session()->getId(),
                'password_fingerprint' => $user->password,
                'security_session_version' => $expectedVersion,
                'created_at' => now()->timestamp,
            ]);

            return view('auth.login', ['sessionConflict' => true]);
        }

        $request->session()->put(SingleDeviceSession::OWNER_TOKEN_KEY, $ownerToken);
        $user->refresh();
        Auth::login($user, $request->boolean('remember'));

        return $this->redirectAuthenticatedUser($request, $invitations, $user);
    }

    private function redirectAuthenticatedUser(Request $request, CustomerInvitationService $invitations, User $user): RedirectResponse
    {
        $intendedUrl = $request->session()->pull('url.intended');
        $invitationPath = $invitations->intendedPathForUser($intendedUrl, $user);

        if ($invitationPath !== null) {
            return redirect()->to($invitationPath);
        }

        $destination = TenantDashboard::destinationFor($user);

        abort_if($destination === null, 403, 'Todavía no tienes una barbería activa asignada.');

        if ($intendedUrl !== null) {
            $allowedIntendedPath = $this->allowedIntendedDashboardPath($intendedUrl, $user);

            if ($allowedIntendedPath !== null) {
                return redirect()->to($allowedIntendedPath);
            }
        }

        return redirect()->route($destination['route'], $destination['parameters']);
    }

    private function allowedIntendedDashboardPath(string $intendedUrl, User $user): ?string
    {
        if ($this->isExternalUrl($intendedUrl)) {
            return null;
        }

        $intendedPath = parse_url($intendedUrl, PHP_URL_PATH) ?: '/';

        if ($intendedPath === route('platform.dashboard', absolute: false)) {
            return $user?->is_platform_admin ? $intendedPath : null;
        }

        if ($intendedPath === route('tenant.selector', absolute: false)) {
            $destination = TenantDashboard::destinationFor($user);

            return ($destination['route'] ?? null) === 'tenant.selector' ? $intendedPath : null;
        }

        $tenantPrefix = '/barbershops/';
        $tenantSuffix = '/dashboard';

        if (! str_starts_with($intendedPath, $tenantPrefix) || ! str_ends_with($intendedPath, $tenantSuffix)) {
            return null;
        }

        $slug = substr($intendedPath, strlen($tenantPrefix), -strlen($tenantSuffix));

        if ($slug === false || $slug === '') {
            return null;
        }

        $barbershop = Barbershop::query()->where('slug', $slug)->first();

        if ($barbershop === null || ! TenantDashboard::canUseTenantDashboard($user, $barbershop)) {
            return null;
        }

        return $intendedPath;
    }

    private function isExternalUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if ($host === null) {
            return false;
        }

        return $host !== parse_url(config('app.url'), PHP_URL_HOST);
    }

    public function confirmSessionTransfer(Request $request, SingleDeviceSession $sessions, CustomerInvitationService $invitations, SecurityAuditLogger $auditLogger, AccountLoginGuard $loginGuard): RedirectResponse
    {
        $pending = $request->session()->pull(SingleDeviceSession::PENDING_KEY);

        abort_unless(
            is_array($pending)
            && ($pending['session_id'] ?? null) === $request->session()->getId()
            && is_int($pending['created_at'] ?? null)
            && $pending['created_at'] >= now()->subMinutes(10)->timestamp,
            419,
        );

        $user = User::query()->findOrFail($pending['user_id']);

        abort_unless(
            isset($pending['password_fingerprint'], $pending['security_session_version'])
            && is_int($pending['security_session_version'])
            && hash_equals($pending['password_fingerprint'], $user->password)
            && $pending['security_session_version'] === (int) $user->getAttribute('security_session_version'),
            419,
        );

        $ownerToken = Str::random(64);

        abort_unless(
            $loginGuard->confirmTransfer($user, $ownerToken, $pending['security_session_version'], $sessions),
            419,
        );

        $request->session()->put(SingleDeviceSession::OWNER_TOKEN_KEY, $ownerToken);
        $user->refresh();
        Auth::login($user, (bool) ($pending['remember'] ?? false));
        $auditLogger->record('session.transfer_confirmed', $user, $user, request: $request);

        return $this->redirectAuthenticatedUser($request, $invitations, $user);
    }

    public function cancelSessionTransfer(Request $request): RedirectResponse
    {
        $request->session()->forget(SingleDeviceSession::PENDING_KEY);

        return redirect()->route('login');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request, SingleDeviceSession $sessions): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null) {
            PushSubscription::query()
                ->where('user_id', $user->getAuthIdentifier())
                ->where('session_hash', hash('sha256', $request->session()->getId()))
                ->delete();
        }

        if ($user !== null) {
            $ownerToken = $request->session()->get(SingleDeviceSession::OWNER_TOKEN_KEY);

            if (is_string($ownerToken)) {
                $sessions->release($user, $ownerToken);
            }
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
