<?php

namespace App\Http\Controllers;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\CustomerHistoryInvitation;
use App\Support\CustomerInvitationService;
use App\Support\TenantDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

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

        abort_if($customer->user_id !== null, 409, 'Esta ficha de cliente ya está vinculada a una cuenta.');

        $validated = $request->validateWithBag('customerInvitation'.$customer->id, [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ]);

        $issued = $invitations->issue($user, $barbershop, $customer, $validated['email']);
        $url = route('customer-invitations.show', $issued['token']);
        $status = 'Invitación enviada a '.$issued['invitation']->email.'. El enlace vence en 48 horas.';

        try {
            Notification::route('mail', $issued['invitation']->email)
                ->notify(new CustomerHistoryInvitation($url, $barbershop->name));
        } catch (TransportExceptionInterface $exception) {
            report($exception);
            $status = 'No se pudo enviar el correo. Puedes copiar el enlace y compartirlo con '.$issued['invitation']->email.'.';
        }

        return redirect()
            ->route($canManageCustomers ? 'tenant.customers.index' : 'tenant.appointments.index', [
                $barbershop,
                ...$request->only(['customers_page', 'sort', 'direction']),
            ])
            ->with('status', $status)
            ->with('invitation_customer_id', $customer->id)
            ->with('invitation_url', $url);
    }
}
