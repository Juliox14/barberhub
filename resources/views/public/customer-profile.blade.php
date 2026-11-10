<x-public-booking-layout :barbershop="$barbershop" :branding="$branding" title="Mi perfil">
    @php
        $initials = collect(preg_split('/\s+/u', trim($customer->name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    @endphp
    <div class="bh-customer-profile" id="profile-content">
        <section class="bh-profile-hero" aria-labelledby="profile-heading">
            <div class="bh-profile-avatar" aria-hidden="true">{{ $initials }}</div>
            <div class="bh-profile-identity">
                <h1 id="profile-heading">{{ $customer->name }}</h1>
                <p>Miembro desde <strong>{{ $customer->created_at->timezone($barbershop->timezone)->locale('es')->translatedFormat('j \d\e F \d\e Y') }}</strong>
                    <span aria-hidden="true">·</span> {{ $visitCount }} {{ \Illuminate\Support\Str::plural('visita', $visitCount) }} en {{ $barbershop->name }}</p>
            </div>
        </section>

        @if (session('status'))
            <p class="bh-profile-success" role="status">{{ session('status') }}</p>
        @endif

        <div class="bh-profile-content">
            <section class="bh-public-panel bh-profile-section" aria-labelledby="history-heading">
                <header class="bh-profile-section-heading">
                    <h2 id="history-heading">Historial de citas</h2>
                    <span>Visitas completadas</span>
                </header>
                @forelse ($appointments as $appointment)
                    <article class="bh-profile-visit">
                        <div class="bh-profile-visit-main">
                            <span class="bh-profile-visit-title"><span class="bh-profile-completed">Completada</span>{{ $appointment->service?->name ?? 'Servicio' }}</span>
                            <span class="bh-profile-visit-meta">
                                {{ $appointment->starts_at->timezone($barbershop->timezone)->locale('es')->translatedFormat('j \d\e F \d\e Y · g:i a') }}
                                <span aria-hidden="true">·</span> {{ $appointment->barber?->display_name ?? 'Profesional' }}
                            </span>
                        </div>
                        @if (in_array($appointment->service_id, $activeServiceIds, true))
                            <a class="bh-profile-action" href="{{ route('public.booking.create', ['barbershop' => $barbershop, 'service_id' => $appointment->service_id, ...((in_array($appointment->barber_id, $activeBarberIds, true)) ? ['barber_id' => $appointment->barber_id] : [])]) }}">Reservar de nuevo</a>
                        @endif
                    </article>
                @empty
                    <p class="bh-profile-empty">Cuando completes tu primera cita, aparecerá aquí.</p>
                @endforelse
                @if ($appointments->hasPages())
                    <nav class="bh-profile-pagination" aria-label="Páginas del historial">{{ $appointments->links() }}</nav>
                @endif
            </section>

            <section class="bh-public-panel bh-profile-section" id="preferences" aria-labelledby="preferences-heading">
                <header class="bh-profile-section-heading">
                    <h2 id="preferences-heading">Preferencias en el salón</h2>
                    <span>Solo para {{ $barbershop->name }}</span>
                </header>
                <form method="POST" action="{{ route('customer.profile.preferences.update', $barbershop) }}" class="bh-profile-form">
                    @csrf
                    @method('PUT')
                    <div class="bh-profile-fields">
                        <label for="preferred-barber">Barbero preferido
                            <select id="preferred-barber" name="preferred_barber_id" aria-describedby="preferred-barber-error">
                                <option value="">Sin preferencia</option>
                                @foreach ($barbers as $barber)
                                    <option value="{{ $barber->id }}" @selected((string) old('preferred_barber_id', $preferences['preferred_barber_id'] ?? '') === (string) $barber->id)>{{ $barber->display_name }}</option>
                                @endforeach
                            </select>
                            @error('preferred_barber_id')<span class="bh-profile-field-error" id="preferred-barber-error">{{ $message }}</span>@enderror
                        </label>
                        <label for="preferred-service">Servicio preferido
                            <select id="preferred-service" name="preferred_service_id" aria-describedby="preferred-service-error">
                                <option value="">Sin preferencia</option>
                                @foreach ($services as $service)
                                    <option value="{{ $service->id }}" @selected((string) old('preferred_service_id', $preferences['preferred_service_id'] ?? '') === (string) $service->id)>{{ $service->name }}</option>
                                @endforeach
                            </select>
                            @error('preferred_service_id')<span class="bh-profile-field-error" id="preferred-service-error">{{ $message }}</span>@enderror
                        </label>
                        <label class="bh-profile-field-full" for="preference-notes">Notas para tus próximas visitas
                            <textarea id="preference-notes" name="notes" maxlength="2000" aria-describedby="preference-notes-help preference-notes-error">{{ old('notes', $preferences['notes'] ?? '') }}</textarea>
                            <span class="bh-profile-helper" id="preference-notes-help">Comparte cualquier detalle que ayude a preparar tu próxima cita.</span>
                            @error('notes')<span class="bh-profile-field-error" id="preference-notes-error">{{ $message }}</span>@enderror
                        </label>
                    </div>
                    <div class="bh-profile-form-footer">
                        <span class="bh-profile-helper">Tus preferencias se guardan para esta barbería.</span>
                        <button class="bh-public-button" type="submit">Guardar preferencias</button>
                    </div>
                </form>
            </section>

            <section class="bh-public-panel bh-profile-section" aria-labelledby="reminders-heading">
                <header class="bh-profile-section-heading">
                    <h2 id="reminders-heading">Recordatorios</h2>
                    <span>Próximamente</span>
                </header>
                <p class="bh-profile-reminder-copy">Los recordatorios para volver a reservar todavía no están disponibles. Esta sección se habilitará cuando estén listos.</p>
            </section>
        </div>
    </div>
</x-public-booking-layout>
