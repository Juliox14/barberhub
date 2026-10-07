<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" active-page="business-hours" :can-manage-members="$canManageMembers">
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Horarios de {{ $barbershop->name }}</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">Define la base semanal de apertura. Excepciones, descansos y horarios por barbero se conectarán después.</p>
            </div>
            <div class="inline-flex w-fit items-center gap-2 rounded-lg border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-300">
                {{ $businessHours->where('closed', false)->count() }} {{ $businessHours->where('closed', false)->count() === 1 ? 'día abierto' : 'días abiertos' }}
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
                <h2 class="font-semibold text-white">Crear horario semanal</h2>
                <p class="mt-1 text-sm text-slate-400">Cada día solo puede configurarse una vez por barbería; el tenant se toma de la URL segura.</p>
            </div>
            <form method="POST" action="{{ route('tenant.business-hours.store', $barbershop) }}" class="grid gap-4 px-5 py-5 sm:px-6 lg:grid-cols-[minmax(160px,0.7fr)_minmax(120px,0.45fr)_minmax(120px,0.45fr)_minmax(130px,0.45fr)_auto] lg:items-end">
                @csrf
                <div>
                    <label for="weekday" class="mb-2 block text-sm font-medium text-slate-200">Día</label>
                    <select id="weekday" name="weekday" required class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100">
                        <option value="">Selecciona un día</option>
                        @foreach ($weekdayLabels as $weekday => $label)
                            <option value="{{ $weekday }}" @selected((string) old('weekday') === (string) $weekday)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="opens_at" class="mb-2 block text-sm font-medium text-slate-200">Abre</label>
                    <input id="opens_at" name="opens_at" type="time" value="{{ old('opens_at') }}" class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white">
                </div>
                <div>
                    <label for="closes_at" class="mb-2 block text-sm font-medium text-slate-200">Cierra</label>
                    <input id="closes_at" name="closes_at" type="time" value="{{ old('closes_at') }}" class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white">
                </div>
                <div>
                    <label for="closed" class="mb-2 block text-sm font-medium text-slate-200">Estado</label>
                    <select id="closed" name="closed" class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100">
                        <option value="0" @selected(old('closed', '0') === '0')>Abierto</option>
                        <option value="1" @selected(old('closed') === '1')>Cerrado</option>
                    </select>
                </div>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-semibold text-[#17130b]">Guardar horario</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-[#111820]">
            <div class="border-b border-slate-800 px-5 py-5 sm:px-6">
                <h2 class="font-semibold text-white">Semana operativa</h2>
                <p class="mt-1 text-sm text-slate-400">Estos horarios serán la base para disponibilidad y cálculo de turnos.</p>
            </div>
            <div class="divide-y divide-slate-800">
                @forelse ($businessHours as $businessHour)
                    <form method="POST" action="{{ route('tenant.business-hours.update', [$barbershop, $businessHour]) }}" class="grid gap-4 px-5 py-5 sm:px-6 lg:grid-cols-[minmax(160px,0.7fr)_minmax(120px,0.45fr)_minmax(120px,0.45fr)_minmax(130px,0.45fr)_auto] lg:items-center">
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="weekday-{{ $businessHour->id }}" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500 lg:sr-only">Día</label>
                            <select id="weekday-{{ $businessHour->id }}" name="weekday" class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100">
                                @foreach ($weekdayLabels as $weekday => $label)
                                    <option value="{{ $weekday }}" @selected($businessHour->weekday === $weekday)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="opens-at-{{ $businessHour->id }}" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500 lg:sr-only">Abre</label>
                            <input id="opens-at-{{ $businessHour->id }}" name="opens_at" type="time" value="{{ $businessHour->opens_at }}" class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white">
                        </div>
                        <div>
                            <label for="closes-at-{{ $businessHour->id }}" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500 lg:sr-only">Cierra</label>
                            <input id="closes-at-{{ $businessHour->id }}" name="closes_at" type="time" value="{{ $businessHour->closes_at }}" class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white">
                        </div>
                        <div>
                            <label for="closed-{{ $businessHour->id }}" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500 lg:sr-only">Estado</label>
                            <select id="closed-{{ $businessHour->id }}" name="closed" class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100">
                                <option value="0" @selected(! $businessHour->closed)>Abierto</option>
                                <option value="1" @selected($businessHour->closed)>Cerrado</option>
                            </select>
                        </div>
                        <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-slate-600 px-3.5 py-2.5 text-sm font-semibold text-slate-200">Actualizar</button>
                    </form>
                @empty
                    <div class="px-5 py-14 text-center sm:px-6">
                        <h3 class="font-semibold text-white">Todavía no hay horarios</h3>
                        <p class="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-400">Crea el primer día de atención para preparar la disponibilidad de la barbería.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-tenant-layout>
