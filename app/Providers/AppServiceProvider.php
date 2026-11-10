<?php

namespace App\Providers;

use App\Events\AccountLoginLocked;
use App\Events\PlatformSecurityAction;
use App\Listeners\RecordContainmentSecurityEvent;
use App\Listeners\RecordSecurityAuditEvent;
use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use App\Support\TenantDashboard;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(AccountLoginLocked::class, [RecordContainmentSecurityEvent::class, 'accountLocked']);
        Event::listen(PlatformSecurityAction::class, [RecordContainmentSecurityEvent::class, 'platformAction']);
        Event::listen(Login::class, [RecordSecurityAuditEvent::class, 'login']);
        Event::listen(Logout::class, [RecordSecurityAuditEvent::class, 'logout']);
        Event::listen(PasswordReset::class, [RecordSecurityAuditEvent::class, 'passwordReset']);
        Event::listen(Lockout::class, [RecordSecurityAuditEvent::class, 'lockout']);

        ViewFacade::composer('components.tenant-layout', function (View $view): void {
            $barbershop = $view->getData()['barbershop'] ?? null;
            $user = request()->user();
            $membership = $barbershop instanceof Barbershop && $user instanceof User
                ? TenantDashboard::membershipFor($user, $barbershop)
                : null;
            $canViewCustomers = $barbershop instanceof Barbershop
                && $user instanceof User
                && ($user->is_platform_admin || in_array($membership?->role, [Membership::ROLE_OWNER, Membership::ROLE_ADMIN, Membership::ROLE_BARBER], true));

            $view->with('canManageBranding', $membership?->role === Membership::ROLE_OWNER);
            $view->with('canViewCustomers', $canViewCustomers);
        });
    }
}
