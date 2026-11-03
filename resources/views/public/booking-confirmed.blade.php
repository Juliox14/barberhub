<x-public-booking-layout :barbershop="$barbershop" :branding="$branding" title="Cita confirmada">
    <section class="bh-public-panel bh-public-summary">
        <h1>¡Tu cita está confirmada!</h1>
        @if ($email_sent)
            <p>Enviamos los detalles a {{ $summary['email'] }}.</p>
        @else
            <p role="status">La cita está reservada, pero no pudimos enviar el correo. Guarda los detalles de esta pantalla; no necesitas reservar de nuevo.</p>
        @endif
        @include('public.booking-details')
        <p>Para consultas o cambios, contacta directamente a la barbería.</p>
        <a class="bh-public-button" href="{{ route('public.booking.create', $barbershop) }}">Volver a la barbería</a>
    </section>
</x-public-booking-layout>
