<x-public-booking-layout :barbershop="$barbershop" :branding="$branding" title="Revisa tu reserva">
    <section class="bh-public-panel bh-public-summary">
        <h1>Revisa tu reserva</h1>
        <p>Este es el profesional que atenderá tu cita. El horario se vuelve a comprobar al confirmar.</p>
        @include('public.booking-details')
        <form method="POST" action="{{ route('public.booking.store', $barbershop) }}" class="bh-public-form">
            @csrf
            <input type="hidden" name="draft_id" value="{{ $draft_id }}">
            <button type="submit" class="bh-public-button">Confirmar mi cita</button>
        </form>
        <a class="bh-public-link" href="{{ route('public.booking.create', ['barbershop' => $barbershop, 'date' => substr($summary['starts_at'], 0, 10)]) }}">Elegir otro horario</a>
        <p>El resumen vence en 10 minutos. Todavía no reservaste este horario.</p>
    </section>
</x-public-booking-layout>
