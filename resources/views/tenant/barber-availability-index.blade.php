<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" active-page="barber-availability" :can-manage-members="$canManageMembers">
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Disponibilidad de {{ $barbershop->name }}</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">Define la semana recurrente por barbero. Descansos, excepciones y reglas de slots se conectarán después.</p>
            </div>
            <button
                type="button"
                class="inline-flex min-h-11 w-fit items-center justify-center rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-semibold text-[#17130b] hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300"
                data-slide-over-trigger
                x-on:click="$dispatch('form-slide-over-open', { id: 'create-availability' })"
            >
                Agregar disponibilidad
            </button>
        </header>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-[#111820]" aria-labelledby="availability-heading">
            <header class="flex flex-col gap-2 border-b border-slate-800 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h2 id="availability-heading" class="font-semibold text-white">Semana por barbero</h2>
                    <p class="mt-1 text-sm text-slate-400">Cada barbero puede tener una franja por día. El estado controla si se usa para agenda.</p>
                </div>
                <span class="inline-flex w-fit items-center rounded-lg border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-300">
                    {{ $barbers->sum(fn ($barber) => $barber->availabilities->where('active', true)->count()) }} franjas activas
                </span>
            </header>
            @if ($barbers->isNotEmpty())
                <div class="divide-y divide-slate-800">
                    @foreach ($barbers as $barber)
                        <section class="px-5 py-5 sm:px-6">
                            <header class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="font-semibold text-white">{{ $barber->display_name }}</h3>
                                    <p class="text-sm text-slate-400">{{ $barber->user->name }} · {{ $barber->active ? 'Activo' : 'Inactivo' }}</p>
                                </div>
                                <span class="text-sm text-slate-400">{{ $barber->availabilities->where('active', true)->count() }} activas</span>
                            </header>
                            @if ($barber->availabilities->isNotEmpty())
                                <div class="hidden grid-cols-[minmax(0,1fr)_8rem_8rem_8rem_auto] gap-4 border-b border-slate-800 px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400 lg:grid">
                                    <span>Día</span>
                                    <span>Inicia</span>
                                    <span>Termina</span>
                                    <span>Estado</span>
                                    <span class="text-right">Acciones</span>
                                </div>
                                <div class="divide-y divide-slate-800">
                                    @foreach ($barber->availabilities as $availability)
                                        <article class="grid gap-3 py-4 lg:grid-cols-[minmax(0,1fr)_8rem_8rem_8rem_auto] lg:items-center">
                                            <p class="font-medium text-slate-100">{{ $weekdayLabels[$availability->weekday] }}</p>
                                            <p class="text-sm text-slate-300">{{ $availability->starts_at }} <span class="text-slate-500 lg:hidden">· inicia</span></p>
                                            <p class="text-sm text-slate-300">{{ $availability->ends_at }} <span class="text-slate-500 lg:hidden">· termina</span></p>
                                            <p class="text-sm {{ $availability->active ? 'text-emerald-200' : 'text-slate-400' }}">{{ $availability->active ? 'Activa' : 'Inactiva' }}</p>
                                            <button
                                                type="button"
                                                class="inline-flex min-h-10 w-full items-center justify-center rounded-lg border border-slate-600 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 lg:w-auto"
                                                data-slide-over-trigger
                                                x-on:click="$dispatch('form-slide-over-open', { id: 'availability-{{ $availability->id }}' })"
                                            >
                                                Editar
                                            </button>
                                        </article>

                                        <x-form-slide-over
                                            id="availability-{{ $availability->id }}"
                                            title="Editar disponibilidad"
                                            description="Actualiza la franja semanal de {{ $barber->display_name }}."
                                            :open="old('form_context') === 'availability-'.$availability->id"
                                        >
                                            <form id="availability-update-{{ $availability->id }}" method="POST" action="{{ route('tenant.barber-availability.update', [$barbershop, $availability]) }}" class="space-y-5">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="form_context" value="availability-{{ $availability->id }}">
                                                <div>
                                                    <label for="weekday-{{ $availability->id }}" class="mb-2 block text-sm font-semibold text-slate-200">Día</label>
                                                    <select id="weekday-{{ $availability->id }}" name="weekday" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                                                        @foreach ($weekdayLabels as $weekday => $label)
                                                            <option value="{{ $weekday }}" @selected((int) (old('form_context') === 'availability-'.$availability->id ? old('weekday', $availability->weekday) : $availability->weekday) === (int) $weekday)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label for="starts-at-{{ $availability->id }}" class="mb-2 block text-sm font-semibold text-slate-200">Inicia</label>
                                                    <input id="starts-at-{{ $availability->id }}" name="starts_at" type="time" value="{{ old('form_context') === 'availability-'.$availability->id ? old('starts_at', $availability->starts_at) : $availability->starts_at }}" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                                                </div>
                                                <div>
                                                    <label for="ends-at-{{ $availability->id }}" class="mb-2 block text-sm font-semibold text-slate-200">Termina</label>
                                                    <input id="ends-at-{{ $availability->id }}" name="ends_at" type="time" value="{{ old('form_context') === 'availability-'.$availability->id ? old('ends_at', $availability->ends_at) : $availability->ends_at }}" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                                                </div>
                                                <div>
                                                    <label for="active-{{ $availability->id }}" class="mb-2 block text-sm font-semibold text-slate-200">Estado</label>
                                                    <select id="active-{{ $availability->id }}" name="active" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                                                        <option value="1" @selected(old('form_context') === 'availability-'.$availability->id ? old('active', $availability->active) : $availability->active)>Activa</option>
                                                        <option value="0" @selected(old('form_context') === 'availability-'.$availability->id ? ! old('active', $availability->active) : ! $availability->active)>Inactiva</option>
                                                    </select>
                                                </div>
                                            </form>
                                            <x-slot:footer>
                                                <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" x-on:click="hidePanel()">Cancelar</button>
                                                <button type="submit" form="availability-update-{{ $availability->id }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-5 py-2.5 text-sm font-semibold text-[#17130b] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Guardar cambios</button>
                                            </x-slot:footer>
                                        </x-form-slide-over>
                                    @endforeach
                                </div>
                            @else
                                <p class="rounded-lg border border-dashed border-slate-700 px-4 py-5 text-sm text-slate-400">Todavía no hay disponibilidad para este barbero.</p>
                            @endif
                        </section>
                    @endforeach
                </div>
            @else
                <div class="px-5 py-14 text-center sm:px-6">
                    <h3 class="font-semibold text-white">Todavía no hay perfiles de barbero</h3>
                    <p class="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-400">Crea perfiles activos antes de configurar disponibilidad.</p>
                </div>
            @endif
        </section>

        <x-form-slide-over
            id="create-availability"
            title="Crear disponibilidad semanal"
            description="Selecciona un barbero activo y un día abierto del horario del negocio."
            :open="old('form_context') === 'create-availability'"
        >
            <form id="availability-create" method="POST" action="{{ route('tenant.barber-availability.store', $barbershop) }}" class="space-y-5">
                @csrf
                <input type="hidden" name="form_context" value="create-availability">
                <div>
                    <label for="barber_id" class="mb-2 block text-sm font-semibold text-slate-200">Barbero</label>
                    <select id="barber_id" name="barber_id" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                        <option value="">Selecciona un barbero activo</option>
                        @foreach ($activeBarbers as $barber)
                            <option value="{{ $barber->id }}" @selected(old('form_context') === 'create-availability' && (string) old('barber_id') === (string) $barber->id)>{{ $barber->display_name }} · {{ $barber->user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="weekday" class="mb-2 block text-sm font-semibold text-slate-200">Día</label>
                    <select id="weekday" name="weekday" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                        <option value="">Selecciona un día abierto</option>
                        @foreach ($openBusinessHours as $businessHour)
                            <option value="{{ $businessHour->weekday }}" @selected(old('form_context') === 'create-availability' && (string) old('weekday') === (string) $businessHour->weekday)>{{ $weekdayLabels[$businessHour->weekday] }} · {{ $businessHour->opens_at }}-{{ $businessHour->closes_at }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="starts_at" class="mb-2 block text-sm font-semibold text-slate-200">Inicia</label>
                    <input id="starts_at" name="starts_at" type="time" value="{{ old('form_context') === 'create-availability' ? old('starts_at') : '' }}" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                </div>
                <div>
                    <label for="ends_at" class="mb-2 block text-sm font-semibold text-slate-200">Termina</label>
                    <input id="ends_at" name="ends_at" type="time" value="{{ old('form_context') === 'create-availability' ? old('ends_at') : '' }}" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                </div>
            </form>
            <x-slot:footer>
                <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" x-on:click="hidePanel()">Cancelar</button>
                <button type="submit" form="availability-create" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-5 py-2.5 text-sm font-semibold text-[#17130b] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Guardar disponibilidad</button>
            </x-slot:footer>
        </x-form-slide-over>
    </div>
</x-tenant-layout>
