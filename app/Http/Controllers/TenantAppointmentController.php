<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use App\Support\AppointmentScheduler;
use App\Support\TenantDashboard;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class TenantAppointmentController extends Controller
{
    public function index(Request $request, Barbershop $barbershop): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $membership = TenantDashboard::membershipFor($user, $barbershop);
        $isPlatformAdmin = $user->is_platform_admin;
        abort_unless($membership !== null || $isPlatformAdmin, 403);

        $barber = null;
        $canManageAppointments = $isPlatformAdmin || in_array($membership?->role, [Membership::ROLE_OWNER, Membership::ROLE_ADMIN], true);
        $canCreateAppointments = $canManageAppointments || $membership?->role === Membership::ROLE_BARBER;

        if (! $canManageAppointments) {
            abort_unless($membership?->role === Membership::ROLE_BARBER, 403);
            $barber = $barbershop->barbers()
                ->where('user_id', $user->id)
                ->where('active', true)
                ->firstOrFail();
        }

        $validated = $request->validate([
            'date' => ['sometimes', 'date_format:Y-m-d'],
        ]);
        $date = $validated['date'] ?? now($barbershop->timezone)->toDateString();

        $appointments = $barbershop->appointments()
            ->with(['barber', 'service', 'customer'])
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->whereDate('starts_at', $date)
            ->when($barber !== null, fn ($query) => $query->where('barber_id', $barber->id))
            ->orderBy('starts_at')
            ->get();

        return view('tenant.appointments-index', [
            'barbershop' => $barbershop,
            'appointments' => $appointments,
            'date' => $date,
            'barbers' => $canManageAppointments ? $barbershop->barbers()->where('active', true)->orderBy('display_name')->get() : collect(),
            'services' => $canCreateAppointments ? $barbershop->services()->where('active', true)->orderBy('name')->get() : collect(),
            'customers' => $canCreateAppointments ? $barbershop->customers()->orderBy('name')->get() : collect(),
            'roleLabel' => TenantDashboard::roleLabel($membership),
            'canManageMembers' => $canManageAppointments,
            'canManageAppointments' => $canManageAppointments,
            'canCreateAppointments' => $canCreateAppointments,
            'barber' => $barber,
        ]);
    }

    public function store(Request $request, Barbershop $barbershop, AppointmentScheduler $scheduler): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $membership = TenantDashboard::membershipFor($user, $barbershop);
        $canManageAppointments = $user->is_platform_admin || in_array($membership?->role, [Membership::ROLE_OWNER, Membership::ROLE_ADMIN], true);
        $isBarber = $membership?->role === Membership::ROLE_BARBER;
        abort_unless($canManageAppointments || $isBarber, 403);

        $barber = $isBarber && ! $canManageAppointments
            ? $barbershop->barbers()->where('user_id', $user->id)->where('active', true)->firstOrFail()
            : null;

        $validated = $request->validate([
            'barber_id' => $canManageAppointments ? [
                'required',
                'integer',
                Rule::exists(Barber::class, 'id')->where(fn ($query) => $query
                    ->where('barbershop_id', $barbershop->id)
                    ->where('active', true)),
            ] : ['prohibited'],

            'service_id' => [
                'required',
                'integer',
                Rule::exists(Service::class, 'id')->where(fn ($query) => $query
                    ->where('barbershop_id', $barbershop->id)
                    ->where('active', true)),
            ],
            'customer_id' => [
                ...($isBarber ? ['required_without:new_customer_name'] : []),
                'nullable',
                'prohibits:new_customer_name',
                'integer',
                Rule::exists(Customer::class, 'id')->where(fn ($query) => $query
                    ->where('barbershop_id', $barbershop->id)),
            ],
            'new_customer_name' => [
                ...($isBarber ? ['required_without:customer_id'] : []),
                'nullable',
                'prohibits:customer_id',
                'string',
                'max:255',
                'not_regex:/^\\s*$/u',
            ],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
        ]);

        $barber ??= $barbershop->barbers()->whereKey($validated['barber_id'])->firstOrFail();
        $service = $barbershop->services()->whereKey($validated['service_id'])->firstOrFail();
        $customer = isset($validated['customer_id'])
            ? $barbershop->customers()->whereKey($validated['customer_id'])->firstOrFail()
            : null;
        $startsAt = Carbon::createFromFormat('Y-m-d\TH:i', $validated['starts_at'], $barbershop->timezone);

        DB::transaction(function () use ($barbershop, $barber, $service, $customer, $validated, $startsAt, $scheduler): void {
            if (isset($validated['new_customer_name'])) {
                $customer = $barbershop->customers()->create([
                    'name' => trim($validated['new_customer_name']),
                    'user_id' => null,
                ]);
            }

            $scheduler->schedule(
                barbershop: $barbershop,
                barber: $barber,
                service: $service,
                customer: $customer,
                startsAt: $startsAt,
            );
        });

        return redirect()
            ->route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => substr($validated['starts_at'], 0, 10)])
            ->with('status', 'Cita creada correctamente.');
    }
}
