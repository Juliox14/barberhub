<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\User;
use App\Support\AppointmentHistory;
use App\Support\AppointmentPushDispatcher;
use App\Support\AppointmentScheduler;
use App\Support\ModuleContainment;
use App\Support\PublicBookingAvailability;
use App\Support\PublicBookingManagement;
use App\Support\TenantBranding;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

final class PublicBookingManagementController extends Controller
{
    public function show(
        Request $request,
        Barbershop $barbershop,
        Appointment $appointment,
        ModuleContainment $containment,
        PublicBookingAvailability $availability,
    ): Response {
        $this->ensureAvailable($barbershop, $containment);
        $appointment = $barbershop->appointments()->with(['service', 'barber', 'customer'])->whereKey($appointment->getKey())->firstOrFail();
        $date = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? $appointment->starts_at->format('Y-m-d');
        $slots = $appointment->status === Appointment::STATUS_SCHEDULED && $this->isManageable($barbershop, $appointment)
            ? $availability->slots($barbershop, $appointment->service, $date, $appointment->barber_id)
            : [];

        return response()->view('public.booking-management', [
            'barbershop' => $barbershop,
            'branding' => TenantBranding::resolve($barbershop),
            'appointment' => $appointment,
            'date' => $date,
            'slots' => $slots,
            'canManage' => $appointment->status === Appointment::STATUS_SCHEDULED && $this->isManageable($barbershop, $appointment),
            'cancelUrl' => URL::signedRoute('public.booking.management.cancel', [$barbershop, $appointment]),
            'dateActionUrl' => URL::signedRoute('public.booking.management.date', [$barbershop, $appointment]),
            'rescheduleUrl' => URL::signedRoute('public.booking.management.reschedule', [$barbershop, $appointment]),

        ])->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function chooseDate(Request $request, Barbershop $barbershop, Appointment $appointment, ModuleContainment $containment): RedirectResponse
    {
        $this->ensureAvailable($barbershop, $containment);
        $appointment = $barbershop->appointments()->whereKey($appointment->id)->firstOrFail();
        abort_unless($this->isManageable($barbershop, $appointment), 404);
        $date = $request->validate(['date' => ['required', 'date_format:Y-m-d']])['date'];

        return redirect()->to(URL::signedRoute('public.booking.management', [$barbershop, $appointment, 'date' => $date]));
    }

    public function cancel(
        Request $request,
        Barbershop $barbershop,
        Appointment $appointment,
        ModuleContainment $containment,
        AppointmentHistory $history,
        AppointmentPushDispatcher $push,
    ): RedirectResponse {
        $this->ensureAvailable($barbershop, $containment);
        $appointment = $barbershop->appointments()->whereKey($appointment->id)->firstOrFail();
        $actor = $this->establishedActor($request, $appointment);
        DB::transaction(function () use ($appointment, $barbershop, $actor, $history): void {
            Barber::query()->whereKey($appointment->barber_id)->lockForUpdate()->firstOrFail();
            $locked = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            $this->assertCustomerCanManage($locked, $barbershop);
            $before = $locked->getRawOriginal('starts_at');
            $locked->update(['status' => Appointment::STATUS_CANCELLED]);
            $history->record($locked, Appointment::STATUS_CANCELLED, $actor === null ? 'guest' : 'customer', $actor, $before, $before, Appointment::STATUS_SCHEDULED, Appointment::STATUS_CANCELLED);
        });
        $appointment->refresh();
        $push->dispatch($appointment, $actor, 'cancelled');

        return redirect()->to(PublicBookingManagement::url($barbershop, $appointment))->with('status', 'Tu cita fue cancelada.');
    }

    public function reschedule(
        Request $request,
        Barbershop $barbershop,
        Appointment $appointment,
        ModuleContainment $containment,
        AppointmentScheduler $scheduler,
        AppointmentHistory $history,
        AppointmentPushDispatcher $push,
    ): RedirectResponse {
        $this->ensureAvailable($barbershop, $containment);
        $appointment = $barbershop->appointments()->whereKey($appointment->id)->firstOrFail();
        $validated = $request->validate(['starts_at' => ['required', 'date_format:Y-m-d\\TH:i']]);
        $startsAt = Carbon::createFromFormat('Y-m-d\\TH:i', $validated['starts_at'], $barbershop->timezone);
        $actor = $this->establishedActor($request, $appointment);
        $changed = DB::transaction(function () use ($appointment, $barbershop, $actor, $startsAt, $scheduler, $history): bool {
            Barber::query()->whereKey($appointment->barber_id)->lockForUpdate()->firstOrFail();
            $locked = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            $this->assertCustomerCanManage($locked, $barbershop);
            $currentStart = Carbon::parse($locked->getRawOriginal('starts_at'), 'UTC')->shiftTimezone($barbershop->timezone);
            if ($currentStart->lessThan(now($barbershop->timezone)->addHours(12))) {
                throw ValidationException::withMessages(['starts_at' => 'Los cambios requieren al menos 12 horas de anticipación.']);
            }
            if ($startsAt->equalTo($currentStart)) {
                return false;
            }
            if (! $startsAt->isFuture()) {
                throw ValidationException::withMessages(['starts_at' => 'Selecciona una fecha y hora futuras.']);
            }
            $before = $locked->getRawOriginal('starts_at');
            $rescheduled = $scheduler->reschedule($locked, $startsAt);
            $history->record($rescheduled, 'rescheduled', $actor === null ? 'guest' : 'customer', $actor, $before, $rescheduled->getRawOriginal('starts_at'), Appointment::STATUS_SCHEDULED, Appointment::STATUS_SCHEDULED);

            return true;
        });
        $appointment->refresh();
        if ($changed) {
            $push->dispatch($appointment, $actor, 'rescheduled');
        }

        return redirect()->to(PublicBookingManagement::url($barbershop, $appointment))->with('status', 'Tu cita fue reprogramada.');
    }

    private function ensureAvailable(Barbershop $barbershop, ModuleContainment $containment): void
    {
        abort_unless($barbershop->status === Barbershop::STATUS_ACTIVE, 404);
        abort_if($containment->isSuspended($barbershop, 'appointments'), 404);
    }

    private function isManageable(Barbershop $barbershop, Appointment $appointment): bool
    {
        if ($appointment->status !== Appointment::STATUS_SCHEDULED) {
            return false;
        }
        $startsAt = Carbon::parse($appointment->getRawOriginal('starts_at'), 'UTC')->shiftTimezone($barbershop->timezone);

        return $startsAt->greaterThanOrEqualTo(now($barbershop->timezone)->addHours(12));
    }

    private function assertCustomerCanManage(Appointment $appointment, Barbershop $barbershop): void
    {
        abort_unless($appointment->barbershop_id === $barbershop->id, 404);
        abort_unless($appointment->status === Appointment::STATUS_SCHEDULED, 404);
        $startsAt = Carbon::parse($appointment->getRawOriginal('starts_at'), 'UTC')->shiftTimezone($barbershop->timezone);
        abort_if($startsAt->lessThan(now($barbershop->timezone)->addHours(12)), 404);
    }

    private function establishedActor(Request $request, Appointment $appointment): ?User
    {
        $user = $request->user();

        return $user !== null && $appointment->customer?->user_id === $user->id ? $user : null;
    }
}
