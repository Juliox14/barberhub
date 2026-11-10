<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" active-page="appointments" :can-manage-members="$canManageMembers">
    <div class="mx-auto max-w-3xl space-y-6">
        <header>
            <a class="text-sm text-amber-200 underline underline-offset-4" href="{{ route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => substr($appointment->getRawOriginal('starts_at'), 0, 10)]) }}">Volver a la agenda</a>
            <h1 class="mt-3 text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Revisar recuperación de cita</h1>
            <p class="mt-2 text-sm leading-6 text-slate-400">Verifica el cambio histórico y registra el motivo antes de restaurar. La fecha y el estado se comprobarán de nuevo al confirmar.</p>
        </header>

        <section class="space-y-4 rounded-xl border border-slate-800 bg-[#111820] p-5 sm:p-6" aria-labelledby="restore-values-heading">
            <h2 id="restore-values-heading" class="font-semibold text-white">{{ $event->label() }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <section class="min-w-0 rounded-lg border border-slate-700 bg-slate-900/50 p-4" aria-labelledby="historical-value-heading">
                    <h3 id="historical-value-heading" class="text-sm font-semibold text-slate-100">Valor histórico que se restaurará</h3>
                    <dl class="mt-3 space-y-3">
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Fecha</dt>
                            <dd class="mt-1 font-semibold tabular-nums text-slate-100">{{ \Illuminate\Support\Carbon::parse($event->getRawOriginal('before_starts_at'))->format('d/m/Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Hora</dt>
                            <dd class="mt-1 font-semibold tabular-nums text-slate-100">{{ \Illuminate\Support\Carbon::parse($event->getRawOriginal('before_starts_at'))->format('H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Estado</dt>
                            <dd class="mt-1">@include('tenant.partials.appointment-status-badge', ['status' => $event->before_status])</dd>
                        </div>
                    </dl>
                </section>
                <section class="min-w-0 rounded-lg border border-amber-300/30 bg-amber-300/5 p-4" aria-labelledby="current-value-heading">
                    <h3 id="current-value-heading" class="text-sm font-semibold text-slate-100">Valor actual (debe coincidir al confirmar)</h3>
                    <dl class="mt-3 space-y-3">
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Fecha</dt>
                            <dd class="mt-1 font-semibold tabular-nums text-slate-100">{{ \Illuminate\Support\Carbon::parse($appointment->getRawOriginal('starts_at'))->format('d/m/Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Hora</dt>
                            <dd class="mt-1 font-semibold tabular-nums text-slate-100">{{ \Illuminate\Support\Carbon::parse($appointment->getRawOriginal('starts_at'))->format('H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Estado</dt>
                            <dd class="mt-1">@include('tenant.partials.appointment-status-badge', ['status' => $appointment->status])</dd>
                        </div>
                    </dl>
                </section>
            </div>
            <div class="space-y-2 text-sm leading-6 text-amber-100" aria-labelledby="recovery-limits-heading">
                <h3 id="recovery-limits-heading" class="font-semibold">Antes de confirmar</h3>
                <ul class="list-disc space-y-1 pl-5">
                    <li>Este historial solo permite recuperar fecha y estado.</li>
                    <li>No registra cambios del barbero, servicio o cliente; esos datos no pueden reconstruirse aquí.</li>
                    <li>Si se vuelve a programar, se validarán horarios, disponibilidad, relaciones y conflictos.</li>
                    <li>Una cita cancelada, completada o marcada como no asistió puede volver a programada y afectará los reportes.</li>
                </ul>
            </div>
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
