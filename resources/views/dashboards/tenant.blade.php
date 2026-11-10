<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" :can-manage-members="$canManageMembers">
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Resumen del negocio</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">{{ $barbershop->name }} · {{ $roleCopy }}</p>
            </div>
            <a href="{{ route('tenant.appointments.index', ['barbershop' => $barbershop, 'create' => 'appointment']) }}" class="inline-flex min-h-11 w-fit shrink-0 items-center justify-center rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-semibold text-[#17130b] hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 focus-visible:ring-offset-[#111820]">Nueva cita +</a>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumen de la barbería">
            <article class="rounded-xl border border-slate-800 bg-[#111820] p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-2">
                        <h2 class="text-sm font-medium text-slate-300">Miembros activos</h2>
                        <p class="text-3xl font-bold tabular-nums tracking-tight text-white">{{ $activeMembers->count() }}</p>
                    </div>
                    <span class="shrink-0 rounded-lg bg-amber-300/10 p-2.5" aria-hidden="true"><img src="{{ asset('images/icons/dashboard-team.png') }}" alt="" width="24" height="24" class="h-6 w-6"></span>
                </div>
                <p class="mt-3 text-xs leading-5 text-slate-400">Datos reales del equipo.</p>
            </article>
            <article class="rounded-xl border border-slate-800 bg-[#111820] p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-2">
                        <h2 class="text-sm font-medium text-slate-300">Barberos activos</h2>
                        <p class="text-3xl font-bold tabular-nums tracking-tight text-white">{{ $activeBarberCount }}</p>
                    </div>
                    <span class="shrink-0 rounded-lg bg-amber-300/10 p-2.5" aria-hidden="true"><img src="{{ asset('images/icons/dashboard-scissors.png') }}" alt="" width="24" height="24" class="h-6 w-6"></span>
                </div>
                <p class="mt-3 text-xs leading-5 text-slate-400">Membresías con rol barbero.</p>
            </article>
            <article class="rounded-xl border border-slate-800 bg-[#111820] p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-2">
                        <h2 class="text-sm font-medium text-slate-300">Miembros inactivos</h2>
                        <p class="text-3xl font-bold tabular-nums tracking-tight text-white">{{ $inactiveMemberCount }}</p>
                    </div>
                    <span class="shrink-0 rounded-lg bg-amber-300/10 p-2.5" aria-hidden="true"><img src="{{ asset('images/icons/dashboard-inactive.png') }}" alt="" width="24" height="24" class="h-6 w-6"></span>
                </div>
                <p class="mt-3 text-xs leading-5 text-slate-400">Estado registrado en la barbería.</p>
            </article>
            <article class="rounded-xl border border-slate-800 bg-[#111820] p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-2">
                        <h2 class="text-sm font-medium text-slate-300">Estado de la barbería</h2>
                        <p class="text-2xl font-bold leading-9 text-white">{{ $barbershop->status === \App\Models\Barbershop::STATUS_ACTIVE ? 'Activa' : 'Inactiva' }}</p>
                    </div>
                    <span class="shrink-0 rounded-lg bg-amber-300/10 p-2.5" aria-hidden="true"><img src="{{ asset('images/icons/dashboard-shop.png') }}" alt="" width="24" height="24" class="h-6 w-6"></span>
                </div>
                <p class="mt-3 break-words text-xs leading-5 text-slate-400">{{ $barbershop->timezone }}</p>
            </article>
        </section>

        <div class="grid items-start gap-6 xl:grid-cols-3">
            <section class="min-w-0 overflow-hidden rounded-xl border border-slate-700 bg-[#111820] xl:col-span-2" aria-labelledby="dashboard-agenda-heading">
                <header class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-800 px-5 py-4 sm:px-6">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="shrink-0 rounded-lg bg-amber-300/10 p-3" aria-hidden="true"><img src="{{ asset('images/icons/dashboard-calendar.png') }}" alt="" width="24" height="24" class="h-6 w-6"></span>
                        <div class="min-w-0 space-y-1">
                            <h2 id="dashboard-agenda-heading" class="text-xl font-bold tracking-tight text-white">Agenda de hoy</h2>
                            <p class="break-words text-sm leading-6 text-slate-400">Citas programadas para hoy en {{ $barbershop->timezone }}.</p>
                        </div>
                    </div>
                    <span class="rounded-full border border-amber-300/20 bg-amber-300/10 px-3 py-1.5 text-xs font-semibold tabular-nums text-amber-200">{{ $todayAppointments->count() }} {{ $todayAppointments->count() === 1 ? 'cita' : 'citas' }}</span>
                </header>
                @if ($todayAppointments->isNotEmpty())
                    <ol class="min-h-64 divide-y divide-slate-800">
                        @foreach ($todayAppointments as $appointment)
                            <li class="grid grid-cols-[3.5rem_minmax(0,1fr)] gap-4 px-5 py-6 sm:grid-cols-[4rem_minmax(0,1fr)_auto] sm:gap-6 sm:px-6">
                                <div class="space-y-1">
                                    <time datetime="{{ $appointment->starts_at->format('Y-m-d\TH:i:s') }}" class="text-lg font-bold tabular-nums text-amber-200">{{ $appointment->starts_at->format('H:i') }}</time>
                                    <p class="text-xs tabular-nums text-slate-400">{{ $appointment->ends_at->format('H:i') }}</p>
                                </div>
                                <div class="relative min-w-0 space-y-3 border-l border-slate-700 pl-5">
                                    <span class="absolute -left-1 top-2 h-2 w-2 rounded-full bg-amber-300" aria-hidden="true"></span>
                                    <div class="space-y-1">
                                        <h3 class="break-words text-base font-semibold leading-6 text-slate-100">{{ $appointment->customer?->name ?? 'Cliente sin registrar' }}</h3>
                                        <p class="break-words text-sm leading-6 text-slate-400"><span class="sr-only">Servicio: </span>{{ $appointment->service->name }}</p>
                                    </div>
                                    <p class="inline-flex max-w-full items-center gap-2 rounded-lg border border-slate-700 bg-slate-800/60 px-2.5 py-1 text-xs leading-5 text-slate-300">
                                        <img src="{{ asset('images/icons/dashboard-scissors.png') }}" alt="" width="16" height="16" class="h-4 w-4 shrink-0" aria-hidden="true">
                                        <span class="break-words"><span class="sr-only">Barbero: </span>{{ $appointment->barber->display_name }}</span>
                                    </p>
                                </div>
                                <a href="{{ route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => $appointment->starts_at->toDateString()]) }}" class="col-start-2 inline-flex min-h-11 w-fit items-center justify-center rounded-lg border border-slate-700 px-3 text-xs font-semibold text-slate-200 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 sm:col-start-auto sm:self-start" aria-label="Ver agenda del {{ $appointment->starts_at->format('d/m/Y') }} a las {{ $appointment->starts_at->format('H:i') }}">Ver agenda</a>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <div class="flex min-h-64 flex-col items-center justify-center gap-3 px-6 py-12 text-center">
                        <h3 class="text-lg font-semibold text-slate-100">No hay citas programadas para hoy.</h3>
                        <p class="max-w-sm text-sm leading-6 text-slate-400">Cuando se agende una cita para hoy, aparecerá aquí con su cliente, servicio y barbero.</p>
                        <a href="{{ route('tenant.appointments.index', $barbershop) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-amber-200 hover:text-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Ver agenda</a>
                    </div>
                @endif
            </section>

            <div class="min-w-0 space-y-4">
                <section class="rounded-xl border border-slate-800 bg-[#111820] p-4" aria-labelledby="dashboard-settings-heading">
                    <header class="flex items-center gap-3">
                        <span class="shrink-0 rounded-lg bg-amber-300/10 p-2.5" aria-hidden="true"><img src="{{ asset('images/icons/dashboard-actions.png') }}" alt="" width="24" height="24" class="h-6 w-6"></span>
                        <div class="min-w-0 space-y-1">
                            <h2 id="dashboard-settings-heading" class="font-semibold text-white">Acciones rápidas</h2>
                            <p class="text-xs leading-5 text-slate-400">Gestiona la configuración operativa de tu barbería.</p>
                        </div>
                    </header>
                    @if ($canManageMembers)
                        <nav class="mt-4 divide-y divide-slate-800 overflow-hidden rounded-lg border border-slate-700" aria-label="Configuración operativa">
                            <a href="{{ route('tenant.services.index', $barbershop) }}" class="flex min-h-11 items-center gap-3 px-3 py-2.5 text-sm font-medium text-slate-200 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-300"><img src="{{ asset('images/icons/dashboard-scissors.png') }}" alt="" width="20" height="20" class="h-5 w-5 shrink-0" aria-hidden="true">Servicios</a>
                            <a href="{{ route('tenant.business-hours.index', $barbershop) }}" class="flex min-h-11 items-center gap-3 px-3 py-2.5 text-sm font-medium text-slate-200 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-300"><img src="{{ asset('images/icons/dashboard-clock.png') }}" alt="" width="20" height="20" class="h-5 w-5 shrink-0" aria-hidden="true">Horarios del negocio</a>
                            <a href="{{ route('tenant.barber-availability.index', $barbershop) }}" class="flex min-h-11 items-center gap-3 px-3 py-2.5 text-sm font-medium text-slate-200 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-300"><img src="{{ asset('images/icons/dashboard-calendar.png') }}" alt="" width="20" height="20" class="h-5 w-5 shrink-0" aria-hidden="true">Disponibilidad por barbero</a>
                        </nav>
                    @else
                        <p class="mt-4 text-sm leading-6 text-slate-400">Tu rol no puede administrar la configuración operativa.</p>
                    @endif
                </section>

                <section class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-800 bg-[#111820] p-4" aria-labelledby="dashboard-status-heading">
                    <div class="flex items-center gap-3">
                        <span class="shrink-0 rounded-lg bg-amber-300/10 p-2.5" aria-hidden="true"><img src="{{ asset('images/icons/dashboard-status.png') }}" alt="" width="24" height="24" class="h-6 w-6"></span>
                        <div class="space-y-1">
                            <h2 id="dashboard-status-heading" class="text-sm font-semibold text-white">Estado operativo</h2>
                            <p class="text-xs leading-5 text-slate-400">Estado registrado de la barbería.</p>
                        </div>
                    </div>
                    <span class="rounded-full px-2.5 py-1.5 text-xs font-semibold {{ $barbershop->status === \App\Models\Barbershop::STATUS_ACTIVE ? 'bg-emerald-400/10 text-emerald-200' : 'bg-rose-400/10 text-rose-200' }}">{{ $barbershop->status === \App\Models\Barbershop::STATUS_ACTIVE ? 'Activa' : 'Inactiva' }}</span>
                </section>
            </div>
        </div>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-[#111820]" aria-labelledby="dashboard-team-heading">
            <header class="flex flex-col gap-4 border-b border-slate-800 px-5 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-3">
                    <span class="shrink-0 rounded-lg bg-amber-300/10 p-3" aria-hidden="true"><img src="{{ asset('images/icons/dashboard-team.png') }}" alt="" width="24" height="24" class="h-6 w-6"></span>
                    <div class="space-y-1">
                        <h2 id="dashboard-team-heading" class="text-xl font-bold tracking-tight text-white">Equipo de barbería</h2>
                        <p class="text-sm leading-6 text-slate-400">Barberos activos registrados actualmente.</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                    <span class="rounded-lg border border-slate-700 px-3 py-2 text-xs tabular-nums text-slate-300">{{ $activeBarberCount }} {{ $activeBarberCount === 1 ? 'barbero activo' : 'barberos activos' }}</span>
                    @if ($canManageMembers)
                        <a href="{{ route('tenant.barbers.index', $barbershop) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-amber-200 hover:text-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Gestionar barberos</a>
                        <a href="{{ route('tenant.members.index', $barbershop) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-slate-300 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Gestionar miembros</a>
                    @endif
                </div>
            </header>
            @if ($activeBarbers->isNotEmpty())
                <div class="hidden grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_auto] gap-6 border-b border-slate-800 px-6 py-3 text-xs font-medium text-slate-400 md:grid" aria-hidden="true">
                    <span>Miembro</span>
                    <span>Rol</span>
                    <span>Estado</span>
                </div>
                <ul class="divide-y divide-slate-800">
                    @foreach ($activeBarbers as $barber)
                        <li class="grid gap-4 px-5 py-5 sm:px-6 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_auto] md:items-center md:gap-6">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-800 text-sm font-bold text-amber-200" aria-hidden="true">{{ str($barber->user->name)->substr(0, 1)->upper() }}</span>
                                <div class="min-w-0 space-y-1">
                                    <p class="break-words text-sm font-semibold text-slate-100">{{ $barber->user->name }}</p>
                                    <p class="break-all text-xs leading-5 text-slate-400">{{ $barber->user->email }}</p>
                                </div>
                            </div>
                            <p class="inline-flex w-fit items-center gap-2 rounded-lg border border-slate-700 bg-slate-800/60 px-2.5 py-1 text-xs text-slate-300"><img src="{{ asset('images/icons/dashboard-scissors.png') }}" alt="" width="16" height="16" class="h-4 w-4" aria-hidden="true"><span class="sr-only">Rol: </span>Barbero</p>
                            <p class="flex items-center gap-2 text-xs font-medium text-emerald-200"><span class="h-2 w-2 rounded-full bg-emerald-400" aria-hidden="true"></span><span class="sr-only">Estado: </span>Activo</p>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="space-y-2 px-5 py-8 sm:px-6">
                    <h3 class="font-semibold text-white">No hay barberos activos registrados todavía</h3>
                    <p class="text-sm leading-6 text-slate-400">Los perfiles activos aparecerán aquí.</p>
                </div>
            @endif
        </section>
    </div>
</x-tenant-layout>
