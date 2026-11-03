<x-public-booking-layout :barbershop="$barbershop" :branding="$branding" title="Detalles de tu cita">
    <section class="bh-public-panel bh-public-summary">
        <h1>Detalles de tu cita</h1>
        <dl>
            <div>
                <dt>Estado</dt>
                <dd>{{ match ($appointment->status) {
                    \App\Models\Appointment::STATUS_SCHEDULED => 'Programada',
                    \App\Models\Appointment::STATUS_COMPLETED => 'Completada',
                    \App\Models\Appointment::STATUS_CANCELLED => 'Cancelada',
                    \App\Models\Appointment::STATUS_NO_SHOW => 'No asistió',
                    default => 'No disponible',
                } }}</dd>
            </div>
            <div>
                <dt>Servicio</dt>
                <dd>{{ $appointment->service->name }}</dd>
            </div>
            <div>
                <dt>Profesional</dt>
                <dd>{{ $appointment->barber->display_name }}</dd>
            </div>
            <div>
                <dt>Fecha y hora</dt>
                <dd>{{ $appointment->starts_at->format('d/m/Y H:i') }} ({{ $barbershop->timezone }})</dd>
            </div>
            <div>
                <dt>Cliente</dt>
                <dd>{{ $appointment->customer?->name ?? '—' }}</dd>
            </div>
        </dl>
        <p>Para cambios, contacta directamente a la barbería.</p>
        <a class="bh-public-button" href="{{ route('public.booking.create', $barbershop) }}">Volver a la barbería</a>
    </section>
</x-public-booking-layout>
