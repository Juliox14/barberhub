<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PlatformBarbershopController;
use App\Http\Controllers\PlatformDashboardController;
use App\Http\Controllers\PlatformMemberController;
use App\Http\Controllers\PlatformSecurityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SecurityAuditLogController;
use App\Http\Controllers\TenantAppointmentController;
use App\Http\Controllers\TenantBarberAvailabilityController;
use App\Http\Controllers\TenantBarberController;
use App\Http\Controllers\TenantBarberServiceController;
use App\Http\Controllers\TenantBrandingController;
use App\Http\Controllers\TenantBusinessHourController;
use App\Http\Controllers\TenantBusinessReportController;
use App\Http\Controllers\TenantCustomerController;
use App\Http\Controllers\TenantCustomerInvitationController;
use App\Http\Controllers\TenantDashboardController;
use App\Http\Controllers\TenantMembershipController;
use App\Http\Controllers\TenantOperationalReportController;
use App\Http\Controllers\TenantReportController;
use App\Http\Controllers\TenantSelectorController;
use App\Http\Controllers\TenantServiceController;
use App\Http\Middleware\EnsureModuleAvailable;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/barbershops/{barbershop:slug}/brand/{kind}', [TenantBrandingController::class, 'asset'])->name('tenant.branding.asset');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('platform.admin')->prefix('platform')->name('platform.')->group(function (): void {
        Route::get('/dashboard', PlatformDashboardController::class)->name('dashboard');
        Route::get('/members', [PlatformMemberController::class, 'index'])->name('members.index');
        Route::get('/security', [PlatformSecurityController::class, 'index'])->name('security.index');
        Route::post('/security/sessions/{user}/revoke', [PlatformSecurityController::class, 'revokeSession'])->name('security.sessions.revoke');
        Route::post('/security/barbershops/{barbershop}/modules/suspend', [PlatformSecurityController::class, 'suspendModule'])->name('security.modules.suspend');
        Route::post('/security/barbershops/{barbershop}/modules/release', [PlatformSecurityController::class, 'releaseModule'])->name('security.modules.release');
        Route::get('/security-audit-logs', [SecurityAuditLogController::class, 'index'])->name('security-audit-logs.index');
        Route::get('/barbershops', [PlatformBarbershopController::class, 'index'])->name('barbershops.index');
        Route::get('/barbershops/create', [PlatformBarbershopController::class, 'create'])->name('barbershops.create');
        Route::post('/barbershops', [PlatformBarbershopController::class, 'store'])->name('barbershops.store');
        Route::get('/barbershops/{barbershop}/edit', [PlatformBarbershopController::class, 'edit'])->name('barbershops.edit');
        Route::put('/barbershops/{barbershop}', [PlatformBarbershopController::class, 'update'])->name('barbershops.update');
        Route::delete('/barbershops/{barbershop}', [PlatformBarbershopController::class, 'destroy'])->name('barbershops.destroy');
    });

    Route::get('/barbershops/select', TenantSelectorController::class)->name('tenant.selector');
    Route::middleware('tenant.member')->prefix('/barbershops/{barbershop:slug}')->group(function (): void {
        Route::get('/dashboard', TenantDashboardController::class)->name('tenant.dashboard');
        Route::get('/branding', [TenantBrandingController::class, 'edit'])->name('tenant.branding.edit');
        Route::put('/branding', [TenantBrandingController::class, 'update'])->name('tenant.branding.update');
        Route::get('/customers', [TenantCustomerController::class, 'index'])->name('tenant.customers.index');
        Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('tenant.push-subscriptions.store');
        Route::delete('/push-subscriptions/{subscription}', [PushSubscriptionController::class, 'destroy'])->name('tenant.push-subscriptions.destroy');
        Route::middleware(EnsureModuleAvailable::class.':members')->group(function (): void {
            Route::get('/members', [TenantMembershipController::class, 'index'])->name('tenant.members.index');
            Route::post('/members', [TenantMembershipController::class, 'store'])->name('tenant.members.store');
            Route::put('/members/{membership}', [TenantMembershipController::class, 'update'])->name('tenant.members.update');
            Route::delete('/members/{membership}', [TenantMembershipController::class, 'destroy'])->name('tenant.members.destroy');
        });
        Route::middleware(EnsureModuleAvailable::class.':barbers')->group(function (): void {
            Route::get('/barbers', [TenantBarberController::class, 'index'])->name('tenant.barbers.index');
            Route::post('/barbers', [TenantBarberController::class, 'store'])->name('tenant.barbers.store');
            Route::post('/barbers/invitations', [TenantBarberController::class, 'invite'])->name('tenant.barbers.invitations.store');
            Route::put('/barbers/{barber}', [TenantBarberController::class, 'update'])->name('tenant.barbers.update');
            Route::get('/barber-services/me', [TenantBarberServiceController::class, 'selfEdit'])->name('tenant.barber-services.self.edit');
            Route::put('/barber-services/me', [TenantBarberServiceController::class, 'selfUpdate'])->name('tenant.barber-services.self.update');
            Route::get('/barbers/{barber}/services', [TenantBarberServiceController::class, 'edit'])->name('tenant.barber-services.edit');
            Route::put('/barbers/{barber}/services', [TenantBarberServiceController::class, 'update'])->name('tenant.barber-services.update');
        });
        Route::middleware(EnsureModuleAvailable::class.':services')->group(function (): void {
            Route::get('/services', [TenantServiceController::class, 'index'])->name('tenant.services.index');
            Route::post('/services', [TenantServiceController::class, 'store'])->name('tenant.services.store');
            Route::put('/services/{service}', [TenantServiceController::class, 'update'])->name('tenant.services.update');
        });
        Route::middleware(EnsureModuleAvailable::class.':appointments')->group(function (): void {
            Route::post('/customers/{customer}/invitations', [TenantCustomerInvitationController::class, 'store'])->name('tenant.customer-invitations.store');
            Route::get('/appointments', [TenantAppointmentController::class, 'index'])->name('tenant.appointments.index');
            Route::get('/reports/summary', [TenantReportController::class, 'summary'])->name('tenant.reports.summary');
            Route::get('/reports/evolution', [TenantOperationalReportController::class, 'evolution'])->name('tenant.reports.evolution');
            Route::get('/reports/demand', [TenantOperationalReportController::class, 'demand'])->name('tenant.reports.demand');
            Route::get('/reports/services', [TenantBusinessReportController::class, 'services'])->name('tenant.reports.services');
            Route::get('/reports/barbers', [TenantBusinessReportController::class, 'barbers'])->name('tenant.reports.barbers');
            Route::get('/reports/customers', [TenantBusinessReportController::class, 'customers'])->name('tenant.reports.customers');
            Route::post('/appointments', [TenantAppointmentController::class, 'store'])->name('tenant.appointments.store');
            Route::get('/appointments/{appointment}/restore', [TenantAppointmentController::class, 'restorePreview'])->name('tenant.appointments.restore.show');
            Route::post('/appointments/{appointment}/restore', [TenantAppointmentController::class, 'restore'])->name('tenant.appointments.restore');
            Route::put('/appointments/{appointment}/reschedule', [TenantAppointmentController::class, 'reschedule'])->name('tenant.appointments.reschedule');
            Route::post('/appointments/{appointment}/cancel', [TenantAppointmentController::class, 'cancel'])->name('tenant.appointments.cancel');
            Route::post('/appointments/{appointment}/complete', [TenantAppointmentController::class, 'complete'])->name('tenant.appointments.complete');
            Route::post('/appointments/{appointment}/no-show', [TenantAppointmentController::class, 'noShow'])->name('tenant.appointments.no-show');
        });
        Route::middleware(EnsureModuleAvailable::class.':business-hours')->group(function (): void {
            Route::get('/business-hours', [TenantBusinessHourController::class, 'index'])->name('tenant.business-hours.index');
            Route::post('/business-hours', [TenantBusinessHourController::class, 'store'])->name('tenant.business-hours.store');
            Route::put('/business-hours/{businessHour}', [TenantBusinessHourController::class, 'update'])->name('tenant.business-hours.update');
        });
        Route::middleware(EnsureModuleAvailable::class.':barber-availability')->group(function (): void {
            Route::get('/barber-availability', [TenantBarberAvailabilityController::class, 'index'])->name('tenant.barber-availability.index');
            Route::post('/barber-availability', [TenantBarberAvailabilityController::class, 'store'])->name('tenant.barber-availability.store');
            Route::put('/barber-availability/{barberAvailability}', [TenantBarberAvailabilityController::class, 'update'])->name('tenant.barber-availability.update');
        });
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::prefix('/barbershops/{barbershop:slug}/book')->name('public.booking.')->group(function (): void {
    Route::get('/', [PublicBookingController::class, 'create'])->name('create');
    Route::post('/summary', [PublicBookingController::class, 'summary'])->middleware('throttle:30,1')->name('summary');
    Route::post('/', [PublicBookingController::class, 'store'])->middleware('throttle:30,1')->name('store');
});

require __DIR__.'/public-availability.php';
require __DIR__.'/customer-profile.php';
require __DIR__.'/public-booking-management.php';
require __DIR__.'/guest-booking-verification.php';
require __DIR__.'/auth.php';
require __DIR__.'/password-policy.php';
