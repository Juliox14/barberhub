<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" active-page="members" :can-manage-members="$canManageMembers">
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Miembros de {{ $barbershop->name }}</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">Administra las personas que pueden operar <span class="font-medium text-slate-200">{{ $barbershop->name }}</span> y su nivel de acceso.</p>
            </div>
            <div class="inline-flex w-fit items-center gap-2 rounded-lg border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-300">
                <svg class="h-4 w-4 text-amber-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 1 0 7.75"/></svg>
                {{ $memberships->count() }} {{ $memberships->count() === 1 ? 'miembro' : 'miembros' }}
            </div>
        </div>

        @if (session('status'))
            <div class="flex items-start gap-3 rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100" role="status">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg>
                <p>{{ session('status') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-400/35 bg-rose-400/10 px-4 py-4 text-sm text-rose-100" role="alert">
                <div class="flex items-center gap-2 font-semibold"><svg class="h-5 w-5 text-rose-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v4m0 4h.01"/></svg>Revisa la información enviada.</div>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-rose-100/85">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-[#111820]">
            <div class="flex flex-col gap-3 border-b border-slate-800 px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                <div class="flex gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-300/10 text-amber-300"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5"/><circle cx="12" cy="12" r="9"/></svg></div>
                    <div><h2 class="font-semibold text-white">Agregar miembro</h2><p class="mt-1 text-sm leading-5 text-slate-400">La persona debe tener una cuenta existente en BarberHub.</p></div>
                </div>
                <p class="max-w-sm text-xs leading-5 text-slate-500 sm:text-right">Si el correo ya pertenece al equipo, se actualizarán su rol y estado.</p>
            </div>
            <form method="POST" action="{{ route('tenant.members.store', $barbershop) }}" class="grid gap-4 px-5 py-5 sm:px-6 lg:grid-cols-[minmax(0,1.65fr)_minmax(0,0.9fr)_minmax(0,0.9fr)_auto] lg:items-end">
                @csrf
                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-slate-200">Correo electrónico</label>
                    <div class="relative"><svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9 6 9-6"/></svg><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" placeholder="nombre@correo.com" class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] py-2.5 pl-10 pr-3 text-sm text-white placeholder:text-slate-500 transition focus:border-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-300/25"></div>
                </div>
                <div>
                    <label for="role" class="mb-2 block text-sm font-medium text-slate-200">Rol</label>
                    <select id="role" name="role" required class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100 transition focus:border-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-300/25">
                        @foreach ($roleLabels as $role => $label)
                            <option value="{{ $role }}" @selected(old('role', \App\Models\Membership::ROLE_BARBER) === $role)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="mb-2 block text-sm font-medium text-slate-200">Estado</label>
                    <select id="status" name="status" class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100 transition focus:border-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-300/25">
                        @foreach ($statusLabels as $status => $label)
                            <option value="{{ $status }}" @selected(old('status', \App\Models\Membership::STATUS_ACTIVE) === $status)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-semibold text-[#17130b] transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 focus-visible:ring-offset-[#111820]"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14m7-7H5"/></svg>Guardar miembro</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-[#111820]">
            <div class="flex flex-col gap-2 border-b border-slate-800 px-5 py-5 sm:px-6">
                <h2 class="font-semibold text-white">Miembros del equipo</h2>
                <p class="text-sm text-slate-400">Cambia el rol o estado de cada persona. Los permisos se aplican desde el servidor.</p>
            </div>

            <div class="divide-y divide-slate-800">
                @forelse ($memberships as $membership)
                    <form method="POST" action="{{ route('tenant.members.update', [$barbershop, $membership]) }}" class="grid gap-4 px-5 py-5 sm:px-6 lg:grid-cols-[minmax(220px,1.45fr)_minmax(140px,0.7fr)_minmax(140px,0.7fr)_auto] lg:items-center">
                        @csrf
                        @method('PUT')
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-800 text-sm font-bold text-amber-200">{{ str($membership->user->name)->substr(0, 1)->upper() }}</div>
                            <div class="min-w-0"><p class="truncate font-medium text-slate-100">{{ $membership->user->name }}</p><p class="truncate text-sm text-slate-400">{{ $membership->user->email }}</p></div>
                        </div>
                        <div>
                            <label for="role-{{ $membership->id }}" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500 lg:sr-only">Rol</label>
                            <select id="role-{{ $membership->id }}" name="role" class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100 transition focus:border-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-300/25">
                                @foreach ($roleLabels as $role => $label)
                                    <option value="{{ $role }}" @selected($membership->role === $role)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="status-{{ $membership->id }}" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500 lg:sr-only">Estado</label>
                            <select id="status-{{ $membership->id }}" name="status" class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100 transition focus:border-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-300/25">
                                @foreach ($statusLabels as $status => $label)
                                    <option value="{{ $status }}" @selected($membership->status === $status)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-wrap gap-2 lg:justify-end">
                            <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-slate-600 px-3.5 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-slate-500 hover:bg-slate-800 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Actualizar</button>
                            <button type="submit" form="membership-delete-{{ $membership->id }}" class="inline-flex items-center justify-center rounded-lg border border-rose-400/30 px-3.5 py-2.5 text-sm font-semibold text-rose-200 transition hover:bg-rose-400/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-300">Eliminar</button>
                        </div>
                    </form>
                    <form id="membership-delete-{{ $membership->id }}" method="POST" action="{{ route('tenant.members.destroy', [$barbershop, $membership]) }}" onsubmit="return confirm('¿Eliminar esta membresía?');" class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                @empty
                    <div class="px-5 py-14 text-center sm:px-6">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-800 text-slate-400"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                        <h3 class="mt-4 font-semibold text-white">Todavía no hay miembros registrados</h3>
                        <p class="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-400">Agrega una cuenta existente para que forme parte del equipo de esta barbería.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-tenant-layout>
