<x-public-booking-layout :barbershop="$barbershop" :branding="$branding">
    <div class="bh-public-intro">
        <div>
            <h1>Tu próxima cita, a tu manera.</h1>
            <p>{{ $branding['description'] ?: 'Elige tu servicio y encuentra un horario con nuestro equipo.' }}</p>
            <p>Puedes reservar sin crear una cuenta.</p>
        </div>
        @if ($barbershop->brand_cover_path)
            <img src="{{ route('tenant.branding.asset', [$barbershop, 'cover']) }}" alt="{{ $barbershop->name }}" class="bh-public-cover">
        @endif
    </div>

    @if ($services->isEmpty())
        <section class="bh-public-panel"><h2>Aún no hay servicios para reservar</h2><p>Contacta a la barbería para consultar su disponibilidad.</p></section>
    @else
        <div class="bh-public-columns">
            <section class="bh-public-panel" aria-labelledby="service-heading">
                <h2 id="service-heading">1. Elige tu servicio y profesional</h2>
                <form method="GET" action="{{ route('public.booking.create', $barbershop) }}" class="bh-public-form">
                    <fieldset>
                        <legend>Servicios disponibles</legend>
                        @foreach ($services as $item)
                            <label class="bh-public-choice">
                                <input type="radio" name="service_id" value="{{ $item->id }}" @checked($service?->id === $item->id) required>
                                <span><strong>{{ $item->name }}</strong><small>{{ $item->description }}</small><small>{{ $item->duration_minutes }} min · {{ $item->price }}</small></span>
                            </label>
                        @endforeach
                    </fieldset>
                    <label>Profesional
                        <select name="barber_id">
                            <option value="">Sin preferencia</option>
                            @foreach ($barbers as $barber)<option value="{{ $barber->id }}" @selected($barberId === $barber->id)>{{ $barber->display_name }}</option>@endforeach
                        </select>
                    </label>
                    <label>Fecha <input type="date" name="date" value="{{ $date }}" min="{{ $today }}" max="{{ $lastDate }}" required></label>
                    <button type="submit" class="bh-public-button">Ver horarios disponibles</button>
                </form>
            </section>
            <section class="bh-public-panel" aria-labelledby="slot-heading">
                <h2 id="slot-heading">2. Selecciona tu horario</h2>
                @if (!$service)
                    <p>Selecciona un servicio, profesional y fecha para ver los horarios.</p>
                @elseif (empty($slots))
                    <p>No hay horarios disponibles para esta selección. Prueba otra fecha o elige “Sin preferencia”.</p>
                @else
                    <p>{{ $service->name }} · {{ $date }} · {{ $barbershop->timezone }}</p>
                    <form method="POST" action="{{ route('public.booking.summary', $barbershop) }}" class="bh-public-form" data-public-booking
                        data-issue-url="{{ route('public.booking.email-code.store', $barbershop) }}" data-verify-url="{{ route('public.booking.email-code.verify', $barbershop) }}">
                        @csrf
                        <input type="hidden" name="service_id" value="{{ $service->id }}">
                        <fieldset><legend>Hora y profesional asignado</legend><div class="bh-public-slots">
                            @foreach ($slots as $slot)
                                <label class="bh-public-choice">
                                    <input type="radio" name="slot" value="{{ $slot['barber_id'] }}|{{ $slot['starts_at'] }}" @checked(old('slot') === $slot['barber_id'].'|'.$slot['starts_at']) required>
                                    <span><strong>{{ substr($slot['starts_at'], 11) }}</strong><small>{{ $slot['barber_name'] }}</small></span>
                                </label>
                            @endforeach
                        </div></fieldset>
                        <h2>3. Tus datos</h2>
                        <label>Nombre <input name="name" autocomplete="name" maxlength="255" value="{{ old('name', auth()->user()?->name) }}" required></label>
                        <label>Correo electrónico <input type="email" name="email" autocomplete="email" maxlength="255" value="{{ old('email', auth()->user()?->email) }}" @readonly($verifiedAccount) required></label>
                        <label>Teléfono <span>(opcional)</span><input type="tel" name="phone" autocomplete="tel" maxlength="40" value="{{ old('phone') }}"></label>
                        @unless ($verifiedAccount)
                            <div class="bh-public-verification">
                                <p>Verifica tu correo para confirmar que podemos contactarte. Tu selección se conserva; el horario se comprobará al confirmar.</p>
                                <button type="button" data-issue-code class="bh-public-button bh-public-button--secondary">Enviar código</button>
                                <label>Código de 6 dígitos <input data-email-code inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6"></label>
                                <button type="button" data-verify-code class="bh-public-button bh-public-button--secondary">Verificar correo</button>
                                <p data-code-status role="status" aria-live="polite"></p>
                                <noscript>Para verificar tu correo, activa JavaScript o inicia sesión con una cuenta de correo verificado.</noscript>
                            </div>
                        @else
                            <p>Tu correo ya está verificado.</p>
                        @endunless
                        <button type="submit" class="bh-public-button">Revisar mi reserva</button>
                    </form>
                @endif
            </section>
        </div>
    @endif
</x-public-booking-layout>
