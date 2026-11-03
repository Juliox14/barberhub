<x-public-booking-layout :barbershop="$barbershop" :branding="$branding">
    <div class="bh-public-wizard" data-booking-wizard data-initial-step="{{ $errors->has('slot') || (old('name') && ! old('slot')) ? 2 : (old('slot') ? 3 : 1) }}">
    <section class="bh-public-intro bh-public-intro--editorial" aria-labelledby="booking-heading">
        <div class="bh-public-intro__copy">
            <p class="bh-public-intro__description">{{ $branding['description'] ?: $barbershop->name }}</p>
            <h1 id="booking-heading">Tu próxima cita,<br><em>a tu manera.</em></h1>
            <p class="bh-public-intro__note">Puedes reservar sin crear una cuenta.</p>
        </div>
        <div class="bh-public-intro__media">
            @if ($barbershop->brand_cover_path)
                <img src="{{ route('tenant.branding.asset', [$barbershop, 'cover']) }}" alt="" class="bh-public-cover">
            @endif
            <div class="bh-public-intro__brand">
                @if ($barbershop->brand_logo_path)
                    <img src="{{ route('tenant.branding.asset', [$barbershop, 'logo']) }}" alt="{{ $barbershop->name }}" width="160" height="160">
                @else
                    <strong>{{ $barbershop->name }}</strong>
                @endif
            </div>
        </div>
    </section>

    @if ($services->isEmpty())
        <section class="bh-public-panel"><h2>Aún no hay servicios para reservar</h2><p>Contacta a la barbería para consultar su disponibilidad.</p></section>
    @else
        @include('public.booking-progress', ['step' => 1])
        <div class="bh-public-columns bh-public-columns--editorial">
            <form method="GET" action="{{ route('public.booking.create', $barbershop) }}" class="bh-public-form bh-public-flow" data-availability data-availability-url="{{ route('public.availability', $barbershop) }}">
                <section aria-labelledby="service-heading" data-step-panel="1" tabindex="-1">
                    <header class="bh-public-section-heading"><span aria-hidden="true">01</span><h2 id="service-heading">Elige tu servicio</h2><small>Servicios disponibles</small></header>
                    <fieldset>
                        <legend class="sr-only">Servicios disponibles</legend>
                        @foreach ($services as $item)
                            <label class="bh-public-choice bh-public-service">
                                <input type="radio" name="service_id" value="{{ $item->id }}" data-service-name="{{ $item->name }}" data-service-price="{{ $item->price }}" data-service-duration="{{ $item->duration_minutes }}" @checked($service?->id === $item->id) required>
                                <span class="bh-public-service__details"><strong>{{ $item->name }}</strong>@if ($item->description)<small>{{ $item->description }}</small>@endif<small>{{ $item->duration_minutes }} min</small></span>
                                <span class="bh-public-service__price">{{ $item->price }}</span>
                            </label>
                        @endforeach
                    </fieldset>
                </section>
                <section aria-labelledby="slot-heading" data-step-panel="2" tabindex="-1">
                    <header class="bh-public-section-heading"><span aria-hidden="true">02</span><h2 id="slot-heading">Encuentra tu horario</h2>
                        <p data-slot-status role="status" aria-live="polite" class="bh-public-slot-status">
                            @if (!$service)
                                Selecciona un servicio y una fecha para ver los horarios disponibles.
                            @elseif (empty($slots))
                                No hay horarios disponibles para esta selección. Prueba otra fecha o elige “Sin preferencia”.
                            @else
                                {{ count($slots) }} horarios disponibles. Elige el que mejor te quede.
                            @endif
                        </p>
                    </header>
                    <div class="bh-public-panel bh-public-schedule">
                        <div class="bh-public-filter-row">
                            <div class="bh-public-date-filter">
                                <span class="bh-public-date-label">Fecha</span>
                                <div class="bh-public-dates" data-date-options role="group" aria-label="Fechas cercanas" hidden></div>
                                <details class="bh-public-date-picker" data-date-picker open>
                                    <summary>Elegir otra fecha</summary>
                                    <label><span class="sr-only">Fecha de la cita</span><input type="date" name="date" value="{{ $date }}" min="{{ $today }}" max="{{ $lastDate }}" required></label>
                                </details>
                            </div>
                            <label>Profesional
                                <select name="barber_id">
                                    <option value="">Sin preferencia</option>
                                    @foreach ($barbers as $barber)<option value="{{ $barber->id }}" @selected($barberId === $barber->id)>{{ $barber->display_name }}</option>@endforeach
                                </select>
                            </label>
                        </div>
                        <button type="submit" class="bh-public-button bh-public-button--secondary bh-public-refresh">Ver horarios disponibles</button>
                        <fieldset data-slot-fieldset @disabled(empty($slots))>
                            <legend>Hora y profesional asignado</legend>
                            <div class="bh-public-slots" data-slot-list aria-busy="false">
                                @foreach ($slots as $slot)
                                    <label class="bh-public-choice">
                                        <input type="radio" name="slot" form="public-booking-form" value="{{ $slot['barber_id'] }}|{{ $slot['starts_at'] }}" @checked(old('slot') === $slot['barber_id'].'|'.$slot['starts_at']) required>
                                        <span><strong>{{ substr($slot['starts_at'], 11) }}</strong><small>{{ $slot['barber_name'] }}</small></span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <p class="bh-public-help">Los horarios se muestran en la zona de {{ $barbershop->timezone }}.</p>
                    </div>
                </section>
            </form>
            <aside id="booking-details" class="bh-public-panel bh-public-reservation" aria-labelledby="reservation-heading" data-step-panel="3" tabindex="-1">
                <header class="bh-public-reservation__heading"><div><span>Paso 03</span><h2 id="reservation-heading">Tu reserva</h2></div><p class="bh-public-reservation__tenant">{{ $barbershop->name }}</p></header>
                <dl class="bh-public-preview">
                    <div>
                        <dt>Servicio</dt>
                        <dd><strong data-booking-service>{{ $service?->name ?? 'Elige un servicio' }}</strong><small data-booking-duration>{{ $service ? $service->duration_minutes.' min' : '—' }}</small></dd>
                        <dd class="bh-public-preview__price" data-booking-price>{{ $service?->price ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Horario</dt>
                        <dd><span data-booking-date>{{ $date }}</span> · <strong data-booking-time>Elige un horario</strong><small data-booking-barber>Profesional pendiente</small></dd>
                    </div>
                </dl>
                <p data-selected-slot class="sr-only" role="status" aria-live="polite">Selecciona un horario para ver tu elección.</p>
                <form id="public-booking-form" method="POST" action="{{ route('public.booking.summary', $barbershop) }}" class="bh-public-form" data-public-booking
                    data-email-verified="{{ $verifiedAccount ? 'true' : 'false' }}" data-availability-ready="{{ empty($slots) ? 'false' : 'true' }}"
                    data-issue-url="{{ route('public.booking.email-code.store', $barbershop) }}" data-verify-url="{{ route('public.booking.email-code.verify', $barbershop) }}">
                    @csrf
                    <input type="hidden" name="service_id" value="{{ $service?->id }}">
                    <h3 class="sr-only">Tus datos</h3>
                    <label>Tu nombre <input name="name" placeholder="Nombre y apellido" autocomplete="name" maxlength="255" value="{{ old('name', auth()->user()?->name) }}" required></label>
                    <label>Correo electrónico <input type="email" name="email" placeholder="tu@correo.com" autocomplete="email" maxlength="255" value="{{ old('email', auth()->user()?->email) }}" @readonly($verifiedAccount) required></label>
                    <label>Teléfono (opcional)<input type="tel" name="phone" placeholder="10 dígitos" autocomplete="tel" maxlength="40" value="{{ old('phone') }}"></label>
                    @unless ($verifiedAccount)
                        <div class="bh-public-verification">
                            <div class="bh-public-verification__controls" data-verification-controls>
                                <p>Para reservar, verifica tu correo con un código. Tu selección se conserva.</p>
                                <button type="button" data-issue-code class="bh-public-button bh-public-button--secondary">Enviar código</button>
                                <label>Código de 6 dígitos <input data-email-code placeholder="6 dígitos" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6"></label>
                                <button type="button" data-verify-code class="bh-public-button bh-public-button--secondary">Verificar correo</button>
                            </div>
                            <p data-code-status role="status" aria-live="polite"></p>
                            <noscript>Para verificar tu correo, activa JavaScript o inicia sesión con una cuenta de correo verificado.</noscript>
                        </div>
                    @else
                        <p class="bh-public-help">Tu correo ya está verificado.</p>
                    @endunless
                    <div class="bh-public-total"><span>Precio del servicio</span><strong data-booking-price>{{ $service?->price ?? '—' }}</strong></div>
                    <button type="submit" class="bh-public-button" data-slot-submit @disabled(empty($slots) || ! $verifiedAccount)>Confirmar reserva</button>
                    <p class="bh-public-help">Revisarás los detalles antes de confirmar tu cita.</p>
                </form>
            </aside>
        </div>
        <nav class="bh-public-mobile-bar" aria-label="Continuar con la reserva">
            <div><strong data-booking-price>{{ $service?->price ?? '—' }}</strong><span data-booking-time>Elige un horario</span><small data-booking-service>{{ $service?->name ?? 'Elige un servicio' }}</small></div>
            <div class="bh-public-wizard-actions">
                <button type="button" class="bh-public-button bh-public-button--secondary" data-wizard-back hidden>Volver</button>
                <button type="button" class="bh-public-button" data-wizard-next hidden>Continuar</button>
                <a href="#booking-details" class="bh-public-button" data-wizard-fallback>Continuar</a>
            </div>
        </nav>
    @endif
    </div>
</x-public-booking-layout>
