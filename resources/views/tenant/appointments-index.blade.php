<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" active-page="appointments" :can-manage-members="$canManageMembers">
    <div class="mx-auto max-w-5xl space-y-5 sm:space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-amber-200">{{ $barbershop->name }}</p>
                <h1 class="mt-1 text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Agenda</h1>
                <p class="mt-1 text-sm leading-6 text-slate-400">{{ $canManageAppointments ? 'Consulta y organiza las citas programadas de la barbería.' : 'Consulta tus citas programadas para este día.' }}</p>
            </div>
            <form method="GET" action="{{ route('tenant.appointments.index', $barbershop) }}" class="flex items-end gap-3">
                <div class="min-w-0 flex-1 sm:flex-none">
                    <label for="date" class="mb-1.5 block text-xs font-medium text-slate-300">Fecha</label>
                    <input id="date" name="date" type="date" value="{{ $date }}" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                </div>
                <button type="submit" class="min-h-11 rounded-lg border border-slate-600 px-3.5 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Ver día</button>
            </form>
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

        @if ($canManageAppointments)
            <x-ui.surface>
                <div class="border-b border-slate-800 px-4 py-4 sm:px-5">
                    <h2 class="font-semibold text-white">Crear cita</h2>
                    <p class="mt-1 text-sm text-slate-400">La duración y disponibilidad se validan según el servicio y los horarios configurados.</p>
                </div>
                <form method="POST" action="{{ route('tenant.appointments.store', $barbershop) }}" class="grid gap-4 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-4 lg:items-end">
                    @csrf
                    <div>
                        <label for="barber_id" class="mb-1.5 block text-sm font-medium text-slate-200">Barbero</label>
                        <select id="barber_id" name="barber_id" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                            <option value="">Seleccionar barbero</option>
                            @foreach ($barbers as $barber)
                                <option value="{{ $barber->id }}" @selected(old('barber_id') == $barber->id)>{{ $barber->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="service_id" class="mb-1.5 block text-sm font-medium text-slate-200">Servicio</label>
                        <select id="service_id" name="service_id" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                            <option value="">Seleccionar servicio</option>
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}" @selected(old('service_id') == $service->id)>{{ $service->name }} · {{ $service->duration_minutes }} min</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="customer_id" class="mb-1.5 block text-sm font-medium text-slate-200">Cliente <span class="font-normal text-slate-400">(opcional)</span></label>
                        <select id="customer_id" name="customer_id" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                            <option value="">Sin cliente</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-3">
                        <div class="min-w-0 flex-1">
                            <label for="starts_at" class="mb-1.5 block text-sm font-medium text-slate-200">Inicio</label>
                            <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $date.'T09:00') }}" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                        </div>
                        <button type="submit" class="min-h-11 shrink-0 rounded-lg bg-amber-300 px-4 py-2 text-sm font-semibold text-[#17130b] hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-100 focus-visible:ring-offset-2 focus-visible:ring-offset-[#111820]">Guardar</button>
                    </div>
                </form>
            </x-ui.surface>
        @endif

        <section aria-labelledby="appointments-heading">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 id="appointments-heading" class="font-semibold text-white">Citas del {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}</h2>
                <span class="text-sm text-slate-400">{{ $appointments->count() }} {{ $appointments->count() === 1 ? 'cita' : 'citas' }}</span>
            </div>
            <div class="space-y-3">
                @forelse ($appointments as $appointment)
                    <x-ui.surface as="article" class="p-4 sm:p-5">
                        <div class="flex items-start gap-3 sm:gap-4">
                            <time datetime="{{ $appointment->starts_at->format('Y-m-d\\TH:i:s') }}" class="w-14 shrink-0 pt-0.5 text-sm font-semibold tabular-nums text-amber-200 sm:w-16 sm:text-base">{{ $appointment->starts_at->format('H:i') }}</time>
                            <div class="min-w-0 flex-1 border-l border-slate-700 pl-3 sm:pl-4">
                                <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                    <div class="min-w-0">
                                        <h3 class="truncate font-semibold text-white">{{ $appointment->customer?->name ?? 'Cliente sin registrar' }}</h3>
                                        <p class="mt-1 text-sm text-slate-300">{{ $appointment->service->name }}</p>
                                    </div>
                                    <span class="w-fit rounded-full border border-slate-700 bg-slate-800/70 px-2.5 py-1 text-xs font-medium text-slate-300">{{ $appointment->barber->display_name }}</span>
                                </div>
                                <p class="mt-2 text-xs text-slate-400">Hasta {{ $appointment->ends_at->format('H:i') }}</p>
                            </div>
                        </div>
                    </x-ui.surface>
                @empty
                    <x-ui.surface class="px-4 py-10 text-center sm:px-6">
                        <h3 class="font-semibold text-white">No hay citas programadas</h3>
                        <p class="mt-1 text-sm text-slate-400">{{ $canManageAppointments ? 'Cuando se agende una cita para este día, aparecerá aquí.' : 'No tienes citas programadas para este día.' }}</p>
                    </x-ui.surface>
                @endforelse
            </div>
        </section>
    </div>
</x-tenant-layout>
