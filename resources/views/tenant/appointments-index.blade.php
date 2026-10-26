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
                @if ($canCreateAppointments)
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
                            <div class="flex flex-wrap gap-2 lg:justify-end">
                                <button type="button" class="inline-flex min-h-11 items-center rounded-lg border border-slate-600 px-3 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" data-slide-over-trigger x-on:click="$dispatch('form-slide-over-open', { id: 'reschedule-appointment-{{ $appointment->id }}' })">Reprogramar</button>
                                <form method="POST" action="{{ route('tenant.appointments.cancel', [$barbershop, $appointment]) }}" onsubmit="return confirm('¿Cancelar esta cita? Esta acción no se puede deshacer.')">
                                    @csrf
                                    <button type="submit" class="inline-flex min-h-11 items-center rounded-lg border border-rose-700 px-3 py-2 text-sm font-semibold text-rose-200 hover:bg-rose-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Cancelar cita</button>
                                </form>
                                <form method="POST" action="{{ route('tenant.appointments.complete', [$barbershop, $appointment]) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-amber-300 px-3 py-2 text-sm font-semibold text-[#17130b] hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Completar</button>
                                </form>
                                <form method="POST" action="{{ route('tenant.appointments.no-show', [$barbershop, $appointment]) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex min-h-11 items-center rounded-lg border border-slate-600 px-3 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">No asistió</button>
                                </form>
                            </div>
                        </article>
                        <x-form-slide-over
                            id="reschedule-appointment-{{ $appointment->id }}"
                            title="Reprogramar cita"
                            description="El horario se valida con la duración del servicio y la disponibilidad del barbero."
                            :open="old('form_context') === 'reschedule-appointment-'.$appointment->id"
                        >
                            <form id="appointment-reschedule-{{ $appointment->id }}" method="POST" action="{{ route('tenant.appointments.reschedule', [$barbershop, $appointment]) }}" class="space-y-5">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="form_context" value="reschedule-appointment-{{ $appointment->id }}">
                                <div>
                                    <label for="starts_at_{{ $appointment->id }}" class="mb-2 block text-sm font-semibold text-slate-200">Nuevo inicio</label>
                                    <input id="starts_at_{{ $appointment->id }}" name="starts_at" type="datetime-local" value="{{ old('form_context') === 'reschedule-appointment-'.$appointment->id ? old('starts_at') : $appointment->starts_at->format('Y-m-d\\TH:i') }}" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                                </div>
                            </form>
                            <x-slot:footer>
                                <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" x-on:click="hidePanel()">Volver</button>
                                <button type="submit" form="appointment-reschedule-{{ $appointment->id }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-5 py-2.5 text-sm font-semibold text-[#17130b] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Guardar horario</button>
                            </x-slot:footer>
                        </x-form-slide-over>
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

        @if ($invitableCustomers->isNotEmpty())
            <section class="space-y-4" aria-labelledby="customer-invitations-heading">
                <header>
                    <h2 id="customer-invitations-heading" class="text-lg font-semibold text-white">Vincular historial de clientes</h2>
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">Crea un enlace personal de un solo uso. El cliente deberá iniciar sesión o registrarse y confirmar la vinculación.</p>
                </header>
                @if (session('invitation_url'))
                    <div class="rounded-lg border border-amber-300/40 bg-amber-300/10 p-4 text-sm text-amber-100" role="status">
                        <p class="font-semibold">Enlace personal listo para compartir</p>
                        <a href="{{ session('invitation_url') }}" class="mt-2 block break-all underline underline-offset-4">{{ url(session('invitation_url')) }}</a>
                    </div>
                @endif
                <div class="divide-y divide-slate-800 overflow-hidden rounded-xl border border-slate-800 bg-[#111820]">
                    @foreach ($invitableCustomers as $customer)
                        <form method="POST" action="{{ route('tenant.customer-invitations.store', [$barbershop, $customer]) }}" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-end sm:justify-between sm:px-6">
                            @csrf
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-100">{{ $customer->name }}</p>
                                <p class="mt-1 text-sm text-slate-400">{{ $customer->email ?: 'Sin correo guardado' }}</p>
                            </div>
                            <div class="flex flex-col gap-2 sm:min-w-80 sm:flex-row sm:items-end">
                                <div class="min-w-0 flex-1">
                                    <label for="invitation-email-{{ $customer->id }}" class="mb-1.5 block text-xs font-medium text-slate-300">Correo de su cuenta</label>
                                    <input id="invitation-email-{{ $customer->id }}" name="email" type="email" value="{{ $customer->email }}" required autocomplete="email" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                                </div>
                                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-600 px-3 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Crear enlace para el cliente</button>
                            </div>
                        </form>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($canCreateAppointments)
            <x-form-slide-over
                id="create-appointment"
                title="Crear cita"
                description="La duración y disponibilidad se validan según el servicio y los horarios configurados."
                :open="old('form_context') === 'create-appointment'"
            >
                <form id="appointment-create" method="POST" action="{{ route('tenant.appointments.store', $barbershop) }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="form_context" value="create-appointment">
                    @if ($canManageAppointments)
                        <div>
                            <label for="barber_id" class="mb-2 block text-sm font-semibold text-slate-200">Barbero</label>
                            <select id="barber_id" name="barber_id" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                                <option value="">Seleccionar barbero</option>
                                @foreach ($barbers as $barberOption)
                                    <option value="{{ $barberOption->id }}" @selected(old('form_context') === 'create-appointment' && (string) old('barber_id') === (string) $barberOption->id)>{{ $barberOption->display_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label for="service_id" class="mb-2 block text-sm font-semibold text-slate-200">Servicio</label>
                        <select id="service_id" name="service_id" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                            <option value="">Seleccionar servicio</option>
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}" @selected(old('form_context') === 'create-appointment' && (string) old('service_id') === (string) $service->id)>{{ $service->name }} · {{ $service->duration_minutes }} min</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="customer_id" class="mb-2 block text-sm font-semibold text-slate-200">Cliente @unless ($canManageAppointments)<span class="font-normal text-slate-400">(elige uno)</span>@else<span class="font-normal text-slate-400">(opcional)</span>@endunless</label>
                        <select id="customer_id" name="customer_id" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                            <option value="">{{ $canManageAppointments ? 'Sin cliente' : 'Seleccionar cliente existente' }}</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" @selected(old('form_context') === 'create-appointment' && (string) old('customer_id') === (string) $customer->id)>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @unless ($canManageAppointments)
                        <div>
                            <label for="new_customer_name" class="mb-2 block text-sm font-semibold text-slate-200">O nombre de cliente nuevo</label>
                            <input id="new_customer_name" name="new_customer_name" type="text" maxlength="255" value="{{ old('form_context') === 'create-appointment' ? old('new_customer_name') : '' }}" autocomplete="off" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                            <p class="mt-1 text-xs text-slate-400">Se guardará como cliente de la barbería, sin crear una cuenta.</p>
                        </div>
                    @endunless
                    <div>
                        <label for="starts_at" class="mb-2 block text-sm font-semibold text-slate-200">Inicio</label>
                        <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('form_context') === 'create-appointment' ? old('starts_at', $date.'T09:00') : $date.'T09:00' }}" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
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
