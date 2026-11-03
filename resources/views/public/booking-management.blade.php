<x-public-booking-layout :barbershop="$barbershop" :branding="$branding" title="Detalles de tu cita">
    <section class="bh-public-panel bh-public-summary">
        <h1>Detalles de tu cita</h1>
        @if (session('status'))
            <p role="status">{{ session('status') }}</p>
        @endif
        <dl>
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
            <h2>Reprogramar cita</h2>
            <form method="POST" action="{{ $dateActionUrl }}">
                @csrf
                <label for="date">Fecha</label>
                <input id="date" name="date" type="date" value="{{ $date }}" required>
                <button type="submit">Ver horarios</button>
            </form>
            <form method="POST" action="{{ $rescheduleUrl }}">
                @csrf
                <label for="starts_at">Horario disponible</label>
                <select id="starts_at" name="starts_at" required>
                    @foreach ($slots as $slot)
                        <option value="{{ $slot['starts_at'] }}" @selected($slot['starts_at'] === $appointment->starts_at->format('Y-m-d\\TH:i'))>{{ $slot['starts_at'] }}</option>
                    @endforeach
                </select>
                @error('starts_at')<p role="alert">{{ $message }}</p>@enderror
                <button type="submit" @disabled($slots === [])>Confirmar reprogramación</button>
            </form>
            <form method="POST" action="{{ $cancelUrl }}">
                @csrf
                <button type="submit">Cancelar cita</button>
            </form>
        @else
            <p>Los cambios requieren al menos 12 horas de anticipación o la cita ya no puede modificarse.</p>
        @endif
        <a class="bh-public-button" href="{{ route('public.booking.create', $barbershop) }}">Volver a la barbería</a>
    </section>
</x-public-booking-layout>
