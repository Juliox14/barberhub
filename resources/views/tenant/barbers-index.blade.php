<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" active-page="barbers" :can-manage-members="$canManageMembers">
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Barberos de {{ $barbershop->name }}</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">Administra los perfiles operativos que podrán aparecer en agenda y servicios.</p>
            </div>
            <div class="inline-flex w-fit items-center gap-2 rounded-lg border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-300">
                {{ $barbers->where('active', true)->count() }} {{ $barbers->where('active', true)->count() === 1 ? 'barbero activo' : 'barberos activos' }}
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
                <h2 class="font-semibold text-white">Crear perfil de barbero</h2>
                <p class="mt-1 text-sm text-slate-400">Selecciona un miembro activo de esta barbería. No se aceptan personas externas ni membresías inactivas.</p>
            </div>
            <form method="POST" action="{{ route('tenant.barbers.store', $barbershop) }}" class="grid gap-4 px-5 py-5 sm:px-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_auto] lg:items-end">
                @csrf
                <div>
                    <label for="user_id" class="mb-2 block text-sm font-medium text-slate-200">Miembro</label>
                    <select id="user_id" name="user_id" required class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100">
                        <option value="">Selecciona un miembro activo</option>
                        @foreach ($eligibleMemberships as $membership)
                            <option value="{{ $membership->user_id }}" @selected((string) old('user_id') === (string) $membership->user_id)>{{ $membership->user->name }} · {{ $membership->user->email }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="display_name" class="mb-2 block text-sm font-medium text-slate-200">Nombre para agenda</label>
                    <input id="display_name" name="display_name" type="text" value="{{ old('display_name') }}" required class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white" placeholder="Ej. Ana Cortes">
                </div>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-semibold text-[#17130b]">Guardar barbero</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-[#111820]">
            <div class="border-b border-slate-800 px-5 py-5 sm:px-6">
                <h2 class="font-semibold text-white">Perfiles de barbero</h2>
                <p class="mt-1 text-sm text-slate-400">El estado activo controla si el perfil se considera disponible para agenda.</p>
            </div>
            <div class="divide-y divide-slate-800">
                @forelse ($barbers as $barber)
                    <form method="POST" action="{{ route('tenant.barbers.update', [$barbershop, $barber]) }}" class="grid gap-4 px-5 py-5 sm:px-6 lg:grid-cols-[minmax(220px,1.3fr)_minmax(180px,1fr)_minmax(140px,0.6fr)_auto] lg:items-center">
                        @csrf
                        @method('PUT')
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-100">{{ $barber->user->name }}</p>
                            <p class="truncate text-sm text-slate-400">{{ $barber->user->email }}</p>
                        </div>
                        <div>
                            <label for="display_name-{{ $barber->id }}" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500 lg:sr-only">Nombre para agenda</label>
                            <input id="display_name-{{ $barber->id }}" name="display_name" type="text" value="{{ $barber->display_name }}" required class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white">
                        </div>
                        <div>
                            <label for="active-{{ $barber->id }}" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500 lg:sr-only">Estado</label>
                            <select id="active-{{ $barber->id }}" name="active" class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100">
                                <option value="1" @selected($barber->active)>Activo</option>
                                <option value="0" @selected(! $barber->active)>Inactivo</option>
                            </select>
                        </div>
                        <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-slate-600 px-3.5 py-2.5 text-sm font-semibold text-slate-200">Actualizar</button>
                    </form>
                @empty
                    <div class="px-5 py-14 text-center sm:px-6">
                        <h3 class="font-semibold text-white">Todavía no hay perfiles de barbero</h3>
                        <p class="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-400">Crea perfiles desde miembros activos para preparar la agenda.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-tenant-layout>
