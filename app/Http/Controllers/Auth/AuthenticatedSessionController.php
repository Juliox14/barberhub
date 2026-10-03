<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Barbershop;
use App\Support\TenantDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        $destination = TenantDashboard::destinationFor($user);

        abort_if($destination === null, 403, 'Todavía no tienes una barbería activa asignada.');

        $intendedUrl = $request->session()->pull('url.intended');
        $allowedIntendedPath = $intendedUrl !== null
            ? $this->allowedIntendedDashboardPath($intendedUrl, $user)
            : null;

        if ($allowedIntendedPath !== null) {
            return redirect()->to($allowedIntendedPath);
        }

        return redirect()->route($destination['route'], $destination['parameters']);
    }

    private function allowedIntendedDashboardPath(string $intendedUrl, mixed $user): ?string
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

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
