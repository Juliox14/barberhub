<nav class="bh-public-progress" aria-label="Progreso de la reserva">
    <p data-wizard-status role="status" aria-live="polite">Paso {{ $step }} de 4 · {{ [1 => 'Seleccionar servicio', 2 => 'Seleccionar fecha y horario', 3 => 'Datos personales y confirmación', 4 => 'Reserva confirmada'][$step] }}</p>
    <ol>
        @foreach ([1 => 'Servicio', 2 => 'Fecha y horario', 3 => 'Tus datos', 4 => 'Confirmada'] as $number => $label)
            <li data-progress-step="{{ $number }}" @if ($number === $step) aria-current="step" @endif><span aria-hidden="true">{{ $number }}</span> {{ $label }}</li>
        @endforeach
    </ol>
</nav>
