<?php

namespace App\Http\Controllers;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use App\Support\CustomerInvitationService;
use App\Support\TenantDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TenantCustomerInvitationController extends Controller
{
    public function store(Request $request, Barbershop $barbershop, int $customer, CustomerInvitationService $invitations): RedirectResponse
    {
        $customer = $barbershop->customers()->whereKey($customer)->firstOrFail();
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $membership = TenantDashboard::membershipFor($user, $barbershop);
        $canManageCustomers = $user->is_platform_admin
            || in_array($membership?->role, [Membership::ROLE_OWNER, Membership::ROLE_ADMIN], true);

        if (! $canManageCustomers) {
            abort_unless($membership?->role === Membership::ROLE_BARBER, 403);
            $barber = $barbershop->barbers()
                ->where('user_id', $user->id)
                ->where('active', true)
                ->firstOrFail();
            abort_unless($customer->appointments()->where('barber_id', $barber->id)->exists(), 403);
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ]);

        $issued = $invitations->issue($user, $barbershop, $customer, $validated['email']);
        $url = route('customer-invitations.show', $issued['token'], false);

        return redirect()
            ->route('tenant.appointments.index', $barbershop)
            ->with('status', 'Enlace personal creado. Compártelo con '.$issued['invitation']->email.' antes de 48 horas.')
            ->with('invitation_url', $url);
    }
}
