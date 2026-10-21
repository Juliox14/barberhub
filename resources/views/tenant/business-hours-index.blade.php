<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" active-page="business-hours" :can-manage-members="$canManageMembers">
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Horarios de {{ $barbershop->name }}</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">Define la base semanal de apertura. Excepciones, descansos y horarios por barbero se conectarán después.</p>
            </div>
            <button
                type="button"
                class="inline-flex min-h-11 w-fit items-center justify-center rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-semibold text-[#17130b] transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300"
                data-slide-over-trigger
                x-on:click="$dispatch('form-slide-over-open', { id: 'create-business-hour' })"
            >
                Agregar horario
            </button>
        </header>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-[#111820]" aria-labelledby="business-hours-heading">
            <header class="flex flex-col gap-2 border-b border-slate-800 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h2 id="business-hours-heading" class="font-semibold text-white">Semana operativa</h2>
                    <p class="mt-1 text-sm text-slate-400">Estos horarios serán la base para disponibilidad y cálculo de turnos.</p>
                </div>
                <span class="inline-flex w-fit items-center rounded-lg border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-300">
                    {{ $businessHours->where('closed', false)->count() }} {{ $businessHours->where('closed', false)->count() === 1 ? 'día abierto' : 'días abiertos' }}
                </span>
            </header>

            @if ($businessHours->isNotEmpty())
                <div class="hidden grid-cols-[minmax(0,1fr)_9rem_9rem_9rem_auto] gap-4 border-b border-slate-800 px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400 lg:grid">
                    <span>Día</span>
                    <span>Abre</span>
                    <span>Cierra</span>
                    <span>Estado</span>
                    <span class="text-right">Acciones</span>
                </div>
                <div class="divide-y divide-slate-800">
                    @foreach ($businessHours as $businessHour)
                        <article class="grid gap-3 px-5 py-5 sm:px-6 lg:grid-cols-[minmax(0,1fr)_9rem_9rem_9rem_auto] lg:items-center">
                            <p class="font-semibold text-slate-100">{{ $weekdayLabels[$businessHour->weekday] }}</p>
                            <p class="text-sm text-slate-300">{{ $businessHour->opens_at ?? '—' }} <span class="text-slate-500 lg:hidden">· abre</span></p>
                            <p class="text-sm text-slate-300">{{ $businessHour->closes_at ?? '—' }} <span class="text-slate-500 lg:hidden">· cierra</span></p>
                            <p class="text-sm {{ $businessHour->closed ? 'text-slate-400' : 'text-emerald-200' }}">{{ $businessHour->closed ? 'Cerrado' : 'Abierto' }}</p>
                            <button
                                type="button"
                                class="inline-flex min-h-10 w-full items-center justify-center rounded-lg border border-slate-600 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 lg:w-auto"
                                data-slide-over-trigger
                                x-on:click="$dispatch('form-slide-over-open', { id: 'business-hour-{{ $businessHour->id }}' })"
                            >
                                Editar
                            </button>
                        </article>

                        <x-form-slide-over
                            id="business-hour-{{ $businessHour->id }}"
                            title="Editar horario"
                            description="Actualiza la apertura semanal de este día."
                            :open="old('form_context') === 'business-hour-'.$businessHour->id"
                        >
                            <form id="business-hour-update-{{ $businessHour->id }}" method="POST" action="{{ route('tenant.business-hours.update', [$barbershop, $businessHour]) }}" class="space-y-5">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="form_context" value="business-hour-{{ $businessHour->id }}">

                                <div>
                                    <label for="weekday-{{ $businessHour->id }}" class="mb-2 block text-sm font-semibold text-slate-200">Día</label>
                                    <select id="weekday-{{ $businessHour->id }}" name="weekday" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                                        @foreach ($weekdayLabels as $weekday => $label)
                                            <option value="{{ $weekday }}" @selected((int) (old('form_context') === 'business-hour-'.$businessHour->id ? old('weekday', $businessHour->weekday) : $businessHour->weekday) === (int) $weekday)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('weekday')
                                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="opens_at-{{ $businessHour->id }}" class="mb-2 block text-sm font-semibold text-slate-200">Abre</label>
                                    <input id="opens_at-{{ $businessHour->id }}" name="opens_at" type="time" value="{{ old('form_context') === 'business-hour-'.$businessHour->id ? old('opens_at', $businessHour->opens_at) : $businessHour->opens_at }}" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                                    @error('opens_at')
                                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="closes_at-{{ $businessHour->id }}" class="mb-2 block text-sm font-semibold text-slate-200">Cierra</label>
                                    <input id="closes_at-{{ $businessHour->id }}" name="closes_at" type="time" value="{{ old('form_context') === 'business-hour-'.$businessHour->id ? old('closes_at', $businessHour->closes_at) : $businessHour->closes_at }}" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                                    @error('closes_at')
                                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="closed-{{ $businessHour->id }}" class="mb-2 block text-sm font-semibold text-slate-200">Estado</label>
                                    <select id="closed-{{ $businessHour->id }}" name="closed" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                                        <option value="0" @selected(old('form_context') === 'business-hour-'.$businessHour->id ? ! old('closed', $businessHour->closed) : ! $businessHour->closed)>Abierto</option>
                                        <option value="1" @selected(old('form_context') === 'business-hour-'.$businessHour->id ? old('closed', $businessHour->closed) : $businessHour->closed)>Cerrado</option>
                                    </select>
                                </div>
                            </form>
                            <x-slot:footer>
                                <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" x-on:click="hidePanel()">Cancelar</button>
                                <button type="submit" form="business-hour-update-{{ $businessHour->id }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-5 py-2.5 text-sm font-semibold text-[#17130b] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Guardar cambios</button>
                            </x-slot:footer>
                        </x-form-slide-over>
                    @endforeach
                </div>
            @else
                <div class="px-5 py-14 text-center sm:px-6">
                    <h3 class="font-semibold text-white">Todavía no hay horarios</h3>
                    <p class="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-400">Crea el primer día de atención para preparar la disponibilidad de la barbería.</p>
                    <button type="button" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-semibold text-[#17130b]" data-slide-over-trigger x-on:click="$dispatch('form-slide-over-open', { id: 'create-business-hour' })">Agregar horario</button>
                </div>
            @endif
        </section>

        <x-form-slide-over
            id="create-business-hour"
            title="Crear horario semanal"
            description="Cada día solo puede configurarse una vez por barbería."
            :open="old('form_context') === 'create-business-hour'"
        >
            <form id="business-hour-create" method="POST" action="{{ route('tenant.business-hours.store', $barbershop) }}" class="space-y-5">
                @csrf
                <input type="hidden" name="form_context" value="create-business-hour">
                <div>
                    <label for="weekday" class="mb-2 block text-sm font-semibold text-slate-200">Día</label>
                    <select id="weekday" name="weekday" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                        <option value="">Selecciona un día</option>
                        @foreach ($weekdayLabels as $weekday => $label)
                            <option value="{{ $weekday }}" @selected((string) (old('form_context') === 'create-business-hour' ? old('weekday') : '') === (string) $weekday)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('weekday')
                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="opens_at" class="mb-2 block text-sm font-semibold text-slate-200">Abre</label>
                    <input id="opens_at" name="opens_at" type="time" value="{{ old('form_context') === 'create-business-hour' ? old('opens_at') : '' }}" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                    @error('opens_at')
                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="closes_at" class="mb-2 block text-sm font-semibold text-slate-200">Cierra</label>
                    <input id="closes_at" name="closes_at" type="time" value="{{ old('form_context') === 'create-business-hour' ? old('closes_at') : '' }}" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                    @error('closes_at')
                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="closed" class="mb-2 block text-sm font-semibold text-slate-200">Estado</label>
                    <select id="closed" name="closed" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                        <option value="0" @selected(old('form_context') === 'create-business-hour' ? old('closed', '0') === '0' : true)>Abierto</option>
                        <option value="1" @selected(old('form_context') === 'create-business-hour' && old('closed') === '1')>Cerrado</option>
                    </select>
                </div>
            </form>
            <x-slot:footer>
                <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" x-on:click="hidePanel()">Cancelar</button>
                <button type="submit" form="business-hour-create" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-5 py-2.5 text-sm font-semibold text-[#17130b] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Guardar horario</button>
            </x-slot:footer>
        </x-form-slide-over>
    </div>
</x-tenant-layout>
