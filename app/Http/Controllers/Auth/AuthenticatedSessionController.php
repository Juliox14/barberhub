<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\RoleDashboard;
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

        $dashboardRoute = RoleDashboard::routeNameFor($request->user());

        abort_if($dashboardRoute === null, 403);

        $intendedUrl = $request->session()->pull('url.intended');

        if ($intendedUrl !== null && ! $this->isOtherRoleDashboardUrl($intendedUrl, $dashboardRoute)) {
            return redirect()->to($intendedUrl);
        }

        return redirect()->route($dashboardRoute);
    }

    private function isOtherRoleDashboardUrl(string $intendedUrl, string $dashboardRoute): bool
    {
        $intendedPath = parse_url($intendedUrl, PHP_URL_PATH) ?: '/';

        foreach (RoleDashboard::routeNamesByRole() as $routeName) {
            if ($intendedPath === route($routeName, absolute: false)) {
                return $routeName !== $dashboardRoute;
            }
        }

        return false;
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
