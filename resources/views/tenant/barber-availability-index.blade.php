<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" active-page="barber-availability" :can-manage-members="$canManageMembers">
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Disponibilidad de {{ $barbershop->name }}</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">Define la semana recurrente por barbero. Descansos, excepciones y reglas de slots se conectarán después.</p>
            </div>
            <div class="inline-flex w-fit items-center gap-2 rounded-lg border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-300">
                {{ $barbers->sum(fn ($barber) => $barber->availabilities->where('active', true)->count()) }} franjas activas
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-400/35 bg-rose-400/10 px-4 py-4 text-sm text-rose-100" role="alert">
                <p class="font-semibold">Revisa la información enviada.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-[#111820]">
            <div class="border-b border-slate-800 px-5 py-5 sm:px-6">
                <h2 class="font-semibold text-white">Crear disponibilidad semanal</h2>
                <p class="mt-1 text-sm text-slate-400">Selecciona un barbero activo y un día abierto del horario del negocio.</p>
            </div>
            <form method="POST" action="{{ route('tenant.barber-availability.store', $barbershop) }}" class="grid gap-4 px-5 py-5 sm:px-6 lg:grid-cols-[minmax(200px,1fr)_minmax(150px,0.7fr)_minmax(120px,0.45fr)_minmax(120px,0.45fr)_auto] lg:items-end">
                @csrf
                <div>
                    <label for="barber_id" class="mb-2 block text-sm font-medium text-slate-200">Barbero</label>
                    <select id="barber_id" name="barber_id" required class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100">
                        <option value="">Selecciona un barbero activo</option>
                        @foreach ($activeBarbers as $barber)
                            <option value="{{ $barber->id }}" @selected((string) old('barber_id') === (string) $barber->id)>{{ $barber->display_name }} · {{ $barber->user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="weekday" class="mb-2 block text-sm font-medium text-slate-200">Día</label>
                    <select id="weekday" name="weekday" required class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100">
                        <option value="">Selecciona un día abierto</option>
                        @foreach ($openBusinessHours as $businessHour)
                            <option value="{{ $businessHour->weekday }}" @selected((string) old('weekday') === (string) $businessHour->weekday)>{{ $weekdayLabels[$businessHour->weekday] }} · {{ $businessHour->opens_at }}-{{ $businessHour->closes_at }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="starts_at" class="mb-2 block text-sm font-medium text-slate-200">Inicia</label>
                    <input id="starts_at" name="starts_at" type="time" value="{{ old('starts_at') }}" required class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white">
                </div>
                <div>
                    <label for="ends_at" class="mb-2 block text-sm font-medium text-slate-200">Termina</label>
                    <input id="ends_at" name="ends_at" type="time" value="{{ old('ends_at') }}" required class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white">
                </div>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-semibold text-[#17130b]">Guardar disponibilidad</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-[#111820]">
            <div class="border-b border-slate-800 px-5 py-5 sm:px-6">
                <h2 class="font-semibold text-white">Semana por barbero</h2>
                <p class="mt-1 text-sm text-slate-400">Cada barbero puede tener una franja por día. El estado controla si se usa para agenda.</p>
            </div>
            <div class="divide-y divide-slate-800">
                @forelse ($barbers as $barber)
                    <div class="px-5 py-5 sm:px-6">
                        <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="font-semibold text-white">{{ $barber->display_name }}</h3>
                                <p class="text-sm text-slate-400">{{ $barber->user->name }} · {{ $barber->active ? 'Activo' : 'Inactivo' }}</p>
                            </div>
                            <span class="text-sm text-slate-500">{{ $barber->availabilities->where('active', true)->count() }} activas</span>
                        </div>
                        <div class="space-y-3">
                            @forelse ($barber->availabilities as $availability)
                                <form method="POST" action="{{ route('tenant.barber-availability.update', [$barbershop, $availability]) }}" class="grid gap-4 rounded-lg border border-slate-800 bg-[#0b1117] p-4 lg:grid-cols-[minmax(150px,0.8fr)_minmax(120px,0.45fr)_minmax(120px,0.45fr)_minmax(130px,0.45fr)_auto] lg:items-center">
                                    @csrf
                                    @method('PUT')
                                    <div>
                                        <label for="weekday-{{ $availability->id }}" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500 lg:sr-only">Día</label>
                                        <select id="weekday-{{ $availability->id }}" name="weekday" class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100">
                                            @foreach ($weekdayLabels as $weekday => $label)
                                                <option value="{{ $weekday }}" @selected($availability->weekday === $weekday)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="starts-at-{{ $availability->id }}" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500 lg:sr-only">Inicia</label>
                                        <input id="starts-at-{{ $availability->id }}" name="starts_at" type="time" value="{{ $availability->starts_at }}" required class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white">
                                    </div>
                                    <div>
                                        <label for="ends-at-{{ $availability->id }}" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500 lg:sr-only">Termina</label>
                                        <input id="ends-at-{{ $availability->id }}" name="ends_at" type="time" value="{{ $availability->ends_at }}" required class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white">
                                    </div>
                                    <div>
                                        <label for="active-{{ $availability->id }}" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500 lg:sr-only">Estado</label>
                                        <select id="active-{{ $availability->id }}" name="active" class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100">
                                            <option value="1" @selected($availability->active)>Activa</option>
                                            <option value="0" @selected(! $availability->active)>Inactiva</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-slate-600 px-3.5 py-2.5 text-sm font-semibold text-slate-200">Actualizar</button>
                                </form>
                            @empty
                                <div class="rounded-lg border border-dashed border-slate-700 px-4 py-5 text-sm text-slate-400">Todavía no hay disponibilidad para este barbero.</div>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-14 text-center sm:px-6">
                        <h3 class="font-semibold text-white">Todavía no hay perfiles de barbero</h3>
                        <p class="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-400">Crea perfiles activos antes de configurar disponibilidad.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-tenant-layout>
