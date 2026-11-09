<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" active-page="appointments" :can-manage-members="$canManageMembers">
    <div class="mx-auto max-w-3xl space-y-6">
        <header>
            <a class="text-sm text-amber-200 underline underline-offset-4" href="{{ route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => substr($appointment->getRawOriginal('starts_at'), 0, 10)]) }}">Volver a la agenda</a>
            <h1 class="mt-3 text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Revisar recuperación de cita</h1>
            <p class="mt-2 text-sm leading-6 text-slate-400">Verifica el cambio histórico y registra el motivo antes de restaurar. La fecha y el estado se comprobarán de nuevo al confirmar.</p>
        </header>

        <section class="space-y-4 rounded-xl border border-slate-800 bg-[#111820] p-5 sm:p-6" aria-labelledby="restore-values-heading">
            <h2 id="restore-values-heading" class="font-semibold text-white">{{ $event->label() }}</h2>
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Valor histórico que se restaurará</dt>
                    <dd class="mt-1 text-slate-100">{{ \Illuminate\Support\Carbon::parse($event->getRawOriginal('before_starts_at'))->format('d/m/Y H:i') }} · {{ $event->before_status }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Valor actual (debe coincidir al confirmar)</dt>
                    <dd class="mt-1 text-slate-100">{{ \Illuminate\Support\Carbon::parse($appointment->getRawOriginal('starts_at'))->format('d/m/Y H:i') }} · {{ $appointment->status }}</dd>
                </div>
            </dl>
            <p class="text-sm leading-6 text-amber-100">Este historial solo permite recuperar fecha y estado. No registra cambios del barbero, servicio o cliente; esos datos no pueden reconstruirse aquí. Si se vuelve a programar, se validarán horarios, disponibilidad, relaciones y conflictos. Una cita cancelada, completada o marcada como no asistió puede volver a programada y afectará los reportes.</p>
        </section>

        <form method="POST" action="{{ route('tenant.appointments.restore', [$barbershop, $appointment]) }}" class="space-y-5 rounded-xl border border-slate-800 bg-[#111820] p-5 sm:p-6">
            @csrf
            <input type="hidden" name="event_id" value="{{ $event->id }}">
            <input type="hidden" name="expected_starts_at" value="{{ $appointment->getRawOriginal('starts_at') }}">
            <input type="hidden" name="expected_status" value="{{ $appointment->status }}">
            <div>
                <label for="reason" class="mb-2 block text-sm font-semibold text-slate-200">Motivo de recuperación</label>
                <textarea id="reason" name="reason" required maxlength="500" rows="4" class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">{{ old('reason') }}</textarea>
                @error('reason')<p class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
                @error('event_id')<p class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => substr($appointment->getRawOriginal('starts_at'), 0, 10)]) }}" class="inline-flex min-h-11 items-center rounded-lg border border-slate-600 px-4 py-2 text-sm font-semibold text-slate-200">Cancelar</a>
                <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-amber-300 px-4 py-2 text-sm font-semibold text-[#17130b]">Confirmar recuperación</button>
            </div>
        </form>
    </div>
</x-tenant-layout>
