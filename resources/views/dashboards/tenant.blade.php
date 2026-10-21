<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" :can-manage-members="$canManageMembers">
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Resumen del negocio</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">{{ $barbershop->name }} · {{ $roleCopy }}</p>
            </div>
            <button type="button" disabled class="inline-flex min-h-11 w-fit items-center gap-2 rounded-lg border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-500" title="Disponible cuando se implemente agenda">Nueva cita · Próximamente</button>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumen de la barbería">
            <article class="rounded-xl border border-slate-800 bg-[#111820] p-5">
                <p class="text-sm font-medium text-slate-300">Miembros activos</p>
                <p class="mt-3 text-4xl font-bold tracking-[-0.04em] text-white">{{ $activeMembers->count() }}</p>
                <p class="mt-4 text-xs text-slate-400">Datos reales del equipo.</p>
            </article>
            <article class="rounded-xl border border-slate-800 bg-[#111820] p-5">
                <p class="text-sm font-medium text-slate-300">Barberos activos</p>
                <p class="mt-3 text-4xl font-bold tracking-[-0.04em] text-white">{{ $activeBarberCount }}</p>
                <p class="mt-4 text-xs text-slate-400">Membresías con rol barbero.</p>
            </article>
            <article class="rounded-xl border border-slate-800 bg-[#111820] p-5">
                <p class="text-sm font-medium text-slate-300">Miembros inactivos</p>
                <p class="mt-3 text-4xl font-bold tracking-[-0.04em] text-white">{{ $inactiveMemberCount }}</p>
                <p class="mt-4 text-xs text-slate-400">Estado registrado en la barbería.</p>
            </article>
            <article class="rounded-xl border border-slate-800 bg-[#111820] p-5">
                <p class="text-sm font-medium text-slate-300">Estado de la barbería</p>
                <p class="mt-3 text-2xl font-bold text-white">{{ $barbershop->status === \App\Models\Barbershop::STATUS_ACTIVE ? 'Activa' : 'Inactiva' }}</p>
                <p class="mt-5 text-xs text-slate-400">{{ $barbershop->timezone }}</p>
            </article>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-[#111820]" aria-labelledby="dashboard-team-heading">
            <header class="flex flex-col gap-2 border-b border-slate-800 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h2 id="dashboard-team-heading" class="font-semibold text-white">Equipo de barbería</h2>
                    <p class="mt-1 text-sm text-slate-400">Barberos activos registrados actualmente.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-300">
                        {{ $activeBarbers->count() }} {{ $activeBarbers->count() === 1 ? 'barbero activo' : 'barberos activos' }}
                    </span>
                    @if ($canManageMembers)
                        <a href="{{ route('tenant.barbers.index', $barbershop) }}" class="text-sm font-semibold text-amber-200 hover:text-amber-100">Gestionar barberos</a>
                        <a href="{{ route('tenant.members.index', $barbershop) }}" class="text-sm font-semibold text-slate-300 hover:text-white">Gestionar miembros</a>
                    @endif
                </div>
            </header>
            @if ($activeBarbers->isNotEmpty())
                <div class="hidden grid-cols-[minmax(0,1fr)_auto] gap-4 border-b border-slate-800 px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400 sm:grid">
                    <span>Miembro</span>
                    <span>Estado</span>
                </div>
                <div class="divide-y divide-slate-800">
                    @foreach ($activeBarbers as $barber)
                        <article class="flex items-center justify-between gap-4 px-5 py-5 sm:px-6">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-800 text-sm font-bold text-amber-200" aria-hidden="true">{{ str($barber->user->name)->substr(0, 1)->upper() }}</span>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-slate-100">{{ $barber->user->name }}</p>
                                    <p class="truncate text-sm text-slate-400">{{ $barber->user->email }}</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-2 text-sm font-medium text-emerald-200">
                                <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                                Activo
                            </span>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="px-5 py-12 text-center sm:px-6">
                    <h3 class="font-semibold text-white">No hay barberos activos registrados todavía</h3>
                    <p class="mt-1 text-sm text-slate-400">Los perfiles activos aparecerán aquí.</p>
                </div>
            @endif
        </section>

        <section class="grid gap-6 xl:grid-cols-2" aria-label="Accesos de operación">
            <article class="overflow-hidden rounded-xl border border-slate-800 bg-[#111820]">
                <header class="border-b border-slate-800 px-5 py-5 sm:px-6">
                    <h2 class="font-semibold text-white">Agenda de hoy</h2>
                    <p class="mt-1 text-sm text-slate-400">La agenda se conectará cuando existan citas y disponibilidad.</p>
                </header>
                <div class="divide-y divide-slate-800">
                    @foreach ([['09:00', 'Primer servicio'], ['10:30', 'Corte de cabello'], ['12:00', 'Servicio programado']] as [$time, $service])
                        <div class="flex items-center gap-4 px-5 py-4 sm:px-6">
                            <time class="font-semibold tabular-nums text-amber-200">{{ $time }}</time>
                            <span class="text-sm text-slate-300">{{ $service }}</span>
                            <span class="ml-auto rounded-lg border border-slate-700 px-2.5 py-1 text-xs text-slate-400">Ejemplo</span>
                        </div>
                    @endforeach
                </div>
            </article>
            <article class="overflow-hidden rounded-xl border border-slate-800 bg-[#111820]">
                <header class="border-b border-slate-800 px-5 py-5 sm:px-6">
                    <h2 class="font-semibold text-white">Configuración operativa</h2>
                    <p class="mt-1 text-sm text-slate-400">Gestiona los datos que preparan la agenda.</p>
                </header>
                @if ($canManageMembers)
                    <div class="divide-y divide-slate-800">
                        <a href="{{ route('tenant.services.index', $barbershop) }}" class="flex min-h-14 items-center justify-between gap-3 px-5 py-4 text-sm font-semibold text-amber-200 hover:bg-slate-900/50 sm:px-6">Servicios <span aria-hidden="true">→</span></a>
                        <a href="{{ route('tenant.business-hours.index', $barbershop) }}" class="flex min-h-14 items-center justify-between gap-3 px-5 py-4 text-sm font-semibold text-amber-200 hover:bg-slate-900/50 sm:px-6">Horarios del negocio <span aria-hidden="true">→</span></a>
                        <a href="{{ route('tenant.barber-availability.index', $barbershop) }}" class="flex min-h-14 items-center justify-between gap-3 px-5 py-4 text-sm font-semibold text-amber-200 hover:bg-slate-900/50 sm:px-6">Disponibilidad por barbero <span aria-hidden="true">→</span></a>
                    </div>
                @else
                    <p class="px-5 py-6 text-sm text-slate-400 sm:px-6">Tu rol no puede administrar la configuración operativa.</p>
                @endif
            </article>
        </section>
        <p class="text-sm leading-6 text-slate-500">Inventario, reportes y configuración adicional se conectarán a medida que se implementen.</p>
    </div>
</x-tenant-layout>
