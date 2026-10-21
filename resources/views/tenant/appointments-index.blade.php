<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" active-page="appointments" :can-manage-members="$canManageMembers">
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Agenda</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">
                    {{ $canManageAppointments ? 'Consulta y organiza las citas programadas de la barbería.' : 'Consulta tus citas programadas para este día.' }}
                </p>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <form method="GET" action="{{ route('tenant.appointments.index', $barbershop) }}" class="flex items-end gap-3">
                    <div>
                        <label for="date" class="mb-1.5 block text-xs font-medium text-slate-300">Fecha</label>
                        <input id="date" name="date" type="date" value="{{ $date }}" class="block min-h-11 rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                    </div>
                    <button type="submit" class="min-h-11 rounded-lg border border-slate-600 px-3.5 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Ver día</button>
                </form>
                @if ($canManageAppointments)
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-4 py-2 text-sm font-semibold text-[#17130b] hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300"
                        data-slide-over-trigger
                        x-on:click="$dispatch('form-slide-over-open', { id: 'create-appointment' })"
                    >
                        Crear cita
                    </button>
                @endif
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100" role="status">
                {{ session('status') }}
            </div>
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

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-[#111820]" aria-labelledby="appointments-heading">
            <header class="flex flex-col gap-2 border-b border-slate-800 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h2 id="appointments-heading" class="font-semibold text-white">Citas del {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}</h2>
                </div>
                <span class="inline-flex w-fit items-center rounded-lg border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-300">
                    {{ $appointments->count() }} {{ $appointments->count() === 1 ? 'cita' : 'citas' }}
                </span>
            </header>
            @if ($appointments->isNotEmpty())
                <div class="hidden grid-cols-[7rem_minmax(0,1fr)_minmax(10rem,0.8fr)_minmax(9rem,0.7fr)] gap-4 border-b border-slate-800 px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400 lg:grid">
                    <span>Hora</span>
                    <span>Cliente y servicio</span>
                    <span>Barbero</span>
                    <span>Horario</span>
                </div>
                <div class="divide-y divide-slate-800">
                    @foreach ($appointments as $appointment)
                        <article class="grid gap-3 px-5 py-5 sm:px-6 lg:grid-cols-[7rem_minmax(0,1fr)_minmax(10rem,0.8fr)_minmax(9rem,0.7fr)] lg:items-center">
                            <time datetime="{{ $appointment->starts_at->format('Y-m-d\\TH:i:s') }}" class="text-lg font-semibold tabular-nums text-amber-200">{{ $appointment->starts_at->format('H:i') }}</time>
                            <div class="min-w-0">
                                <h3 class="truncate font-semibold text-slate-100">{{ $appointment->customer?->name ?? 'Cliente sin registrar' }}</h3>
                                <p class="mt-1 text-sm text-slate-400">{{ $appointment->service->name }}</p>
                            </div>
                            <p class="text-sm text-slate-300">{{ $appointment->barber->display_name }}</p>
                            <p class="text-sm text-slate-400">Hasta {{ $appointment->ends_at->format('H:i') }}</p>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="px-5 py-14 text-center sm:px-6">
                    <h3 class="font-semibold text-white">No hay citas programadas</h3>
                    <p class="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-400">
                        {{ $canManageAppointments ? 'Cuando se agende una cita para este día, aparecerá aquí.' : 'No tienes citas programadas para este día.' }}
                    </p>
                </div>
            @endif
        </section>

        @if ($canManageAppointments)
            <x-form-slide-over
                id="create-appointment"
                title="Crear cita"
                description="La duración y disponibilidad se validan según el servicio y los horarios configurados."
                :open="old('form_context') === 'create-appointment'"
            >
                <form id="appointment-create" method="POST" action="{{ route('tenant.appointments.store', $barbershop) }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="form_context" value="create-appointment">
                    <div>
                        <label for="barber_id" class="mb-2 block text-sm font-semibold text-slate-200">Barbero</label>
                        <select id="barber_id" name="barber_id" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                            <option value="">Seleccionar barbero</option>
                            @foreach ($barbers as $barber)
                                <option value="{{ $barber->id }}" @selected(old('form_context') === 'create-appointment' && (string) old('barber_id') === (string) $barber->id)>{{ $barber->display_name }}</option>
                            @endforeach
                        </select>
                        @error('barber_id')
                            <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="service_id" class="mb-2 block text-sm font-semibold text-slate-200">Servicio</label>
                        <select id="service_id" name="service_id" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                            <option value="">Seleccionar servicio</option>
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}" @selected(old('form_context') === 'create-appointment' && (string) old('service_id') === (string) $service->id)>{{ $service->name }} · {{ $service->duration_minutes }} min</option>
                            @endforeach
                        </select>
                        @error('service_id')
                            <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="customer_id" class="mb-2 block text-sm font-semibold text-slate-200">Cliente <span class="font-normal text-slate-400">(opcional)</span></label>
                        <select id="customer_id" name="customer_id" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                            <option value="">Sin cliente</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" @selected(old('form_context') === 'create-appointment' && (string) old('customer_id') === (string) $customer->id)>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="starts_at" class="mb-2 block text-sm font-semibold text-slate-200">Inicio</label>
                        <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('form_context') === 'create-appointment' ? old('starts_at', $date.'T09:00') : $date.'T09:00' }}" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                        @error('starts_at')
                            <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>
                </form>
                <x-slot:footer>
                    <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" x-on:click="hidePanel()">Cancelar</button>
                    <button type="submit" form="appointment-create" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-5 py-2.5 text-sm font-semibold text-[#17130b] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Guardar cita</button>
                </x-slot:footer>
            </x-form-slide-over>
        @endif
    </div>
</x-tenant-layout>
