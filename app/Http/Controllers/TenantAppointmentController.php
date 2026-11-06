<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use App\Support\AppointmentHistory;
use App\Support\AppointmentPushDispatcher;
use App\Support\AppointmentScheduler;
use App\Support\TableSort;
use App\Support\TenantDashboard;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        $sortColumns = [
            'time' => 'appointments.starts_at',
            'customer' => static function ($query, string $direction): void {
                $query->orderBy(Customer::query()->select('name')->whereColumn('customers.id', 'appointments.customer_id'), $direction);
            },
            'service' => static function ($query, string $direction): void {
                $query->orderBy(Service::query()->select('name')->whereColumn('services.id', 'appointments.service_id'), $direction);
            },
            'barber' => static function ($query, string $direction): void {
                $query->orderBy(Barber::query()->select('display_name')->whereColumn('barbers.id', 'appointments.barber_id'), $direction);
            },
        ];
        $sortState = TableSort::state($request, $sortColumns, 'time');
        $appointmentsQuery = $barbershop->appointments()->getQuery()
            ->with(['barber', 'service', 'customer'])
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->whereDate('starts_at', $date)
            ->when($barber !== null, fn ($query) => $query->where('barber_id', $barber->id));
        if ($sortState['requested']) {
            TableSort::apply($appointmentsQuery, $sortColumns, $sortState['sort'], $sortState['direction']);
        } else {
            $appointmentsQuery->orderBy('starts_at')->orderBy('appointments.id');
        }
        $appointments = $appointmentsQuery->get();

        return view('tenant.appointments-index', [
            'barbershop' => $barbershop,
            'appointments' => $appointments,
            'sortState' => $sortState,
            'date' => $date,
            'barbers' => $canManageAppointments ? $barbershop->barbers()->where('active', true)->orderBy('display_name')->get() : collect(),
            'services' => $canCreateAppointments ? $barbershop->services()->where('active', true)->orderBy('name')->get() : collect(),
            'customers' => $canCreateAppointments ? $barbershop->customers()->orderBy('name')->get() : collect(),
            'invitableCustomers' => $canManageAppointments
                ? $barbershop->customers()->orderBy('name')->get()
                : ($barber !== null
                    ? $barbershop->customers()
                        ->whereHas('appointments', fn ($query) => $query->where('barber_id', $barber->id))
                        ->orderBy('name')
                        ->get()
                    : collect()),
            'roleLabel' => TenantDashboard::roleLabel($membership),
            'canManageMembers' => $canManageAppointments,
            'canManageAppointments' => $canManageAppointments,
            'canCreateAppointments' => $canCreateAppointments,
            'barber' => $barber,
        ]);
    }

    public function store(Request $request, Barbershop $barbershop, AppointmentScheduler $scheduler, AppointmentPushDispatcher $pushDispatcher, AppointmentHistory $history): RedirectResponse
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

        $appointment = DB::transaction(function () use ($barbershop, $barber, $service, $customer, $validated, $startsAt, $scheduler, $history, $user): Appointment {
            if (isset($validated['new_customer_name'])) {
                $customer = $barbershop->customers()->create([
                    'name' => trim($validated['new_customer_name']),
                    'user_id' => null,
                ]);
            }

            $appointment = $scheduler->schedule(
                barbershop: $barbershop,
                barber: $barber,
                service: $service,
                customer: $customer,
                startsAt: $startsAt,
            );
            $history->record($appointment, 'created', 'staff', $user, null, $appointment->getRawOriginal('starts_at'), null, Appointment::STATUS_SCHEDULED);

            return $appointment;
        });

        $pushDispatcher->dispatch($appointment, $user, 'assigned');

        return redirect()
            ->route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => substr($validated['starts_at'], 0, 10)])
            ->with('status', 'Cita creada correctamente.');
    }

    public function reschedule(Request $request, Barbershop $barbershop, Appointment $appointment, AppointmentScheduler $scheduler, AppointmentPushDispatcher $pushDispatcher, AppointmentHistory $history): RedirectResponse
    {
        $appointment = $this->authorizeAppointmentAction($request, $barbershop, $appointment);
        $validated = $request->validate([
            'starts_at' => ['required', 'date_format:Y-m-d\\TH:i'],
        ]);
        $startsAt = Carbon::createFromFormat('Y-m-d\\TH:i', $validated['starts_at'], $barbershop->timezone);
        $user = $request->user();
        $appointment = DB::transaction(function () use ($appointment, $startsAt, $scheduler, $history, $user): Appointment {
            Barber::query()->whereKey($appointment->barber_id)->lockForUpdate()->firstOrFail();
            $locked = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            $before = $locked->getRawOriginal('starts_at');
            $rescheduled = $scheduler->reschedule($locked, $startsAt);
            if ($before !== $rescheduled->getRawOriginal('starts_at')) {
                $history->record($rescheduled, 'rescheduled', 'staff', $user, $before, $rescheduled->getRawOriginal('starts_at'), Appointment::STATUS_SCHEDULED, Appointment::STATUS_SCHEDULED);
            }

            return $rescheduled;
        });
        abort_unless($user instanceof User, 403);
        $pushDispatcher->dispatch($appointment, $user, 'rescheduled');

        return redirect()
            ->route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => substr($validated['starts_at'], 0, 10)])
            ->with('status', 'Cita reprogramada correctamente.');
    }

    public function cancel(Request $request, Barbershop $barbershop, Appointment $appointment, AppointmentPushDispatcher $pushDispatcher, AppointmentHistory $history): RedirectResponse
    {
        return $this->transition($request, $barbershop, $appointment, Appointment::STATUS_CANCELLED, 'Cita cancelada.', $pushDispatcher, $history);
    }

    public function complete(Request $request, Barbershop $barbershop, Appointment $appointment, AppointmentHistory $history): RedirectResponse
    {
        return $this->transition($request, $barbershop, $appointment, Appointment::STATUS_COMPLETED, 'Cita marcada como completada.', null, $history);
    }

    public function noShow(Request $request, Barbershop $barbershop, Appointment $appointment, AppointmentHistory $history): RedirectResponse
    {
        return $this->transition($request, $barbershop, $appointment, Appointment::STATUS_NO_SHOW, 'Cita marcada como no asistió.', null, $history);
    }

    private function transition(Request $request, Barbershop $barbershop, Appointment $appointment, string $status, string $message, ?AppointmentPushDispatcher $pushDispatcher, AppointmentHistory $history): RedirectResponse
    {
        $appointment = $this->authorizeAppointmentAction($request, $barbershop, $appointment);

        DB::transaction(function () use ($appointment, $barbershop, $status, $history, $request): void {
            Barber::query()->whereKey($appointment->barber_id)->lockForUpdate()->firstOrFail();
            $lockedAppointment = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            abort_unless($lockedAppointment->status === Appointment::STATUS_SCHEDULED, 404);
            if (in_array($status, [Appointment::STATUS_COMPLETED, Appointment::STATUS_NO_SHOW], true) && ! $lockedAppointment->hasStarted($barbershop->timezone)) {
                throw ValidationException::withMessages(['starts_at' => 'La asistencia solo se puede confirmar después de la hora de inicio de la cita.']);
            }
            $before = $lockedAppointment->getRawOriginal('starts_at');
            $lockedAppointment->update(['status' => $status]);
            $history->record($lockedAppointment, $status, 'staff', $request->user(), $before, $before, Appointment::STATUS_SCHEDULED, $status);
        });

        if ($status === Appointment::STATUS_CANCELLED && $pushDispatcher !== null) {
            $user = $request->user();
            abort_unless($user instanceof User, 403);
            $pushDispatcher->dispatch($appointment, $user, 'cancelled');
        }

        return redirect()
            ->route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => $appointment->starts_at->toDateString()])
            ->with('status', $message);
    }

    private function authorizeAppointmentAction(Request $request, Barbershop $barbershop, Appointment $appointment): Appointment
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $appointment = $barbershop->appointments()->whereKey($appointment->id)->firstOrFail();
        $membership = TenantDashboard::membershipFor($user, $barbershop);
        $canManageAppointments = $user->is_platform_admin || in_array($membership?->role, [Membership::ROLE_OWNER, Membership::ROLE_ADMIN], true);

        if (! $canManageAppointments) {
            abort_unless($membership?->role === Membership::ROLE_BARBER, 403);
            $barber = $barbershop->barbers()->where('user_id', $user->id)->where('active', true)->firstOrFail();
            abort_unless($appointment->barber_id === $barber->id, 403);
        }

        abort_unless($appointment->status === Appointment::STATUS_SCHEDULED, 404);

        return $appointment;
    }
}
