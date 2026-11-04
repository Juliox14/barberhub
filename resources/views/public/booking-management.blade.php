<x-public-booking-layout :barbershop="$barbershop" :branding="$branding" title="Detalles de tu cita">
    <section class="bh-public-panel bh-public-summary bh-public-management">
        <h1>Detalles de tu cita</h1>
        @if (session('status'))
            <p class="bh-public-management__notice" role="status">{{ session('status') }}</p>
        @endif
        <dl class="bh-public-details">
            <div><dt>Estado</dt><dd>{{ match ($appointment->status) {
                \App\Models\Appointment::STATUS_SCHEDULED => 'Programada',
                \App\Models\Appointment::STATUS_COMPLETED => 'Completada',
                \App\Models\Appointment::STATUS_CANCELLED => 'Cancelada',
                \App\Models\Appointment::STATUS_NO_SHOW => 'No asistió',
                default => 'No disponible',
            } }}</dd></div>
            <div><dt>Servicio</dt><dd>{{ $appointment->service->name }}</dd></div>
            <div><dt>Profesional</dt><dd>{{ $appointment->barber->display_name }}</dd></div>
            <div><dt>Fecha y hora</dt><dd>{{ $appointment->starts_at->format('d/m/Y H:i') }} ({{ $barbershop->timezone }})</dd></div>
            <div><dt>Cliente</dt><dd>{{ $appointment->customer?->name ?? '—' }}</dd></div>
        </dl>
        @if ($canManage)
            <div class="bh-public-management__actions" x-data="{}">
                <section class="bh-public-management__reschedule" aria-labelledby="reschedule-heading">
                    <h2 id="reschedule-heading">Reprogramar cita</h2>
                    <form method="POST" action="{{ $dateActionUrl }}">
                        @csrf
                        <label for="date">Fecha</label>
                        <input id="date" name="date" type="date" value="{{ $date }}" required>
                        <button class="bh-public-button bh-public-button--secondary" type="submit">Ver horarios</button>
                    </form>
                    <form method="POST" action="{{ $rescheduleUrl }}">
                        @csrf
                        <label for="starts_at">Horario disponible</label>
                        <select id="starts_at" name="starts_at" required>
                            @foreach ($slots as $slot)
                                <option value="{{ $slot['starts_at'] }}" @selected($slot['starts_at'] === $appointment->starts_at->format('Y-m-d\\TH:i'))>{{ $slot['starts_at'] }}</option>
                            @endforeach
                        </select>
                        @error('starts_at')<p class="bh-public-management__error" role="alert">{{ $message }}</p>@enderror
                        <button class="bh-public-button" type="submit" @disabled($slots === [])>Confirmar reprogramación</button>
                    </form>
                </section>
                <div class="bh-public-management__footer">
                    <button class="bh-public-button bh-public-management__cancel" type="button" x-on:click="$refs.cancelConfirmation.showModal()">Cancelar cita</button>
                    <a class="bh-public-button bh-public-button--secondary" href="{{ route('public.booking.create', $barbershop) }}">Volver a la barbería</a>
                </div>
                <dialog class="bh-public-management__dialog" x-ref="cancelConfirmation" aria-labelledby="cancel-heading" aria-describedby="cancel-description">
                    <h2 id="cancel-heading">¿Cancelar tu cita?</h2>
                    <p id="cancel-description">Se cancelará tu reserva y se liberará el horario. Si prefieres mantenerla, selecciona «Conservar cita».</p>
                    <form method="POST" action="{{ $cancelUrl }}" class="bh-public-management__dialog-actions">
                        @csrf
                        <button class="bh-public-button bh-public-button--secondary" type="button" autofocus x-on:click="$refs.cancelConfirmation.close()">Conservar cita</button>
                        <button class="bh-public-button bh-public-management__cancel" type="submit">Sí, cancelar cita</button>
                    </form>
                </dialog>
            </div>
        @else
            <p class="bh-public-management__notice">Los cambios requieren al menos 12 horas de anticipación o la cita ya no puede modificarse.</p>
            <a class="bh-public-button bh-public-button--secondary" href="{{ route('public.booking.create', $barbershop) }}">Volver a la barbería</a>
        @endif
    </section>
</x-public-booking-layout>
