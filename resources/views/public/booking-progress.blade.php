<nav class="bh-public-progress" aria-label="Progreso de la reserva">
    <p data-wizard-status role="status" aria-live="polite">{{ $confirmed ?? false ? 'Paso 4 de 4 · Reserva confirmada' : 'Paso '.$step.' de 4 · '.[1 => 'Seleccionar profesional', 2 => 'Seleccionar servicio', 3 => 'Fecha y horario', 4 => 'Datos personales y confirmación'][$step] }}</p>
    <ol>
        @foreach ([1 => 'Profesional', 2 => 'Servicio', 3 => 'Fecha y horario', 4 => 'Tus datos'] as $number => $label)
            <li data-progress-step="{{ $number }}" @if ($number === $step) aria-current="step" @endif><span aria-hidden="true">{{ $number }}</span> {{ $label }}</li>
        @endforeach
    </ol>
</nav>
