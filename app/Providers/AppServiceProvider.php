<?php

namespace App\Providers;

use App\Events\AccountLoginLocked;
use App\Events\PlatformSecurityAction;
use App\Listeners\RecordContainmentSecurityEvent;
use App\Listeners\RecordSecurityAuditEvent;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

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
    }
}
