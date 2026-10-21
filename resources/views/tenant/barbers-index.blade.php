<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" active-page="barbers" :can-manage-members="$canManageMembers">
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Barberos de {{ $barbershop->name }}</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">Administra los perfiles operativos que podrán aparecer en agenda y servicios.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <button type="button" class="inline-flex min-h-11 w-fit items-center justify-center rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-semibold text-[#17130b] hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" data-slide-over-trigger x-on:click="$dispatch('form-slide-over-open', { id: 'create-barber-invitation' })">Invitar y crear cuenta nueva</button>
                <button type="button" class="inline-flex min-h-11 w-fit items-center justify-center rounded-lg border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-200 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" data-slide-over-trigger x-on:click="$dispatch('form-slide-over-open', { id: 'create-barber-profile' })">Crear perfil para miembro existente</button>
            </div>
        </header>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-[#111820]" aria-labelledby="barbers-heading">
            <header class="flex flex-col gap-2 border-b border-slate-800 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h2 id="barbers-heading" class="font-semibold text-white">Perfiles de barbero</h2>
                    <p class="mt-1 text-sm text-slate-400">El estado activo controla si el perfil se considera disponible para agenda.</p>
                </div>
                <span class="inline-flex w-fit items-center rounded-lg border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-300">
                    {{ $barbers->where('active', true)->count() }} {{ $barbers->where('active', true)->count() === 1 ? 'barbero activo' : 'barberos activos' }}
                </span>
            </header>
            @if ($barbers->isNotEmpty())
                <div class="hidden grid-cols-[minmax(0,1fr)_minmax(0,1fr)_8rem_auto] gap-4 border-b border-slate-800 px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400 lg:grid">
                    <span>Miembro</span>
                    <span>Nombre para agenda</span>
                    <span>Estado</span>
                    <span class="text-right">Acciones</span>
                </div>
                <div class="divide-y divide-slate-800">
                    @foreach ($barbers as $barber)
                        <article class="grid gap-3 px-5 py-5 sm:px-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_8rem_auto] lg:items-center">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-800 font-bold text-amber-200" aria-hidden="true">{{ str($barber->user->name)->substr(0, 1)->upper() }}</span>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-slate-100">{{ $barber->user->name }}</p>
                                    <p class="truncate text-sm text-slate-400">{{ $barber->user->email }}</p>
                                </div>
                            </div>
                            <p class="text-sm text-slate-200">{{ $barber->display_name }}</p>
                            <p class="text-sm {{ $barber->active ? 'text-emerald-200' : 'text-slate-400' }}">{{ $barber->active ? 'Activo' : 'Inactivo' }}</p>
                            <button
                                type="button"
                                class="inline-flex min-h-10 w-full items-center justify-center rounded-lg border border-slate-600 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 lg:w-auto"
                                data-slide-over-trigger
                                x-on:click="$dispatch('form-slide-over-open', { id: 'barber-{{ $barber->id }}' })"
                            >
                                Editar
                            </button>
                        </article>

                        <x-form-slide-over
                            id="barber-{{ $barber->id }}"
                            title="Editar perfil de barbero"
                            description="Actualiza el nombre y disponibilidad de este perfil."
                            :open="old('form_context') === 'barber-'.$barber->id"
                        >
                            <form id="barber-update-{{ $barber->id }}" method="POST" action="{{ route('tenant.barbers.update', [$barbershop, $barber]) }}" class="space-y-5">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="form_context" value="barber-{{ $barber->id }}">
                                <div>
                                    <p class="font-semibold text-white">{{ $barber->user->name }}</p>
                                    <p class="text-sm text-slate-400">{{ $barber->user->email }}</p>
                                </div>
                                <div>
                                    <label for="display_name-{{ $barber->id }}" class="mb-2 block text-sm font-semibold text-slate-200">Nombre para agenda</label>
                                    <input id="display_name-{{ $barber->id }}" name="display_name" type="text" value="{{ old('form_context') === 'barber-'.$barber->id ? old('display_name', $barber->display_name) : $barber->display_name }}" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                                </div>
                                <div>
                                    <label for="active-{{ $barber->id }}" class="mb-2 block text-sm font-semibold text-slate-200">Estado</label>
                                    <select id="active-{{ $barber->id }}" name="active" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                                        <option value="1" @selected(old('form_context') === 'barber-'.$barber->id ? old('active', $barber->active) : $barber->active)>Activo</option>
                                        <option value="0" @selected(old('form_context') === 'barber-'.$barber->id ? ! old('active', $barber->active) : ! $barber->active)>Inactivo</option>
                                    </select>
                                </div>
                            </form>
                            <x-slot:footer>
                                <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" x-on:click="hidePanel()">Cancelar</button>
                                <button type="submit" form="barber-update-{{ $barber->id }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-5 py-2.5 text-sm font-semibold text-[#17130b] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Guardar cambios</button>
                            </x-slot:footer>
                        </x-form-slide-over>
                    @endforeach
                </div>
            @else
                <div class="px-5 py-14 text-center sm:px-6">
                    <h3 class="font-semibold text-white">Todavía no hay perfiles de barbero</h3>
                    <p class="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-400">Crea perfiles desde miembros activos para preparar la agenda.</p>
                    <div class="mt-5 flex flex-wrap justify-center gap-3">
                        <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-semibold text-[#17130b]" data-slide-over-trigger x-on:click="$dispatch('form-slide-over-open', { id: 'create-barber-invitation' })">Invitar barbero</button>
                        <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-200" data-slide-over-trigger x-on:click="$dispatch('form-slide-over-open', { id: 'create-barber-profile' })">Crear desde miembro</button>
                    </div>
                </div>
            @endif
        </section>

        <x-form-slide-over
            id="create-barber-invitation"
            title="Invitar y crear cuenta"
            description="Se crearán la membresía y el perfil activo en esta barbería."
            :open="old('form_context') === 'create-barber-invitation'"
        >
            <p class="mb-6 text-sm leading-6 text-slate-400">La persona recibirá un enlace para configurar su contraseña y verificar su correo.</p>
            <form id="barber-invitation-create" method="POST" action="{{ route('tenant.barbers.invitations.store', $barbershop) }}" class="space-y-5">
                @csrf
                <input type="hidden" name="form_context" value="create-barber-invitation">
                <div>
                    <label for="account_name" class="mb-2 block text-sm font-semibold text-slate-200">Nombre completo</label>
                    <input id="account_name" name="account_name" type="text" value="{{ old('form_context') === 'create-barber-invitation' ? old('account_name') : '' }}" required autocomplete="name" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                </div>
                <div>
                    <label for="account_email" class="mb-2 block text-sm font-semibold text-slate-200">Correo electrónico</label>
                    <input id="account_email" name="account_email" type="email" value="{{ old('form_context') === 'create-barber-invitation' ? old('account_email') : '' }}" required autocomplete="email" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                </div>
                <div>
                    <label for="agenda_name" class="mb-2 block text-sm font-semibold text-slate-200">Nombre para agenda</label>
                    <input id="agenda_name" name="agenda_name" type="text" value="{{ old('form_context') === 'create-barber-invitation' ? old('agenda_name') : '' }}" required placeholder="Ej. Ana Cortes" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                </div>
            </form>
            <x-slot:footer>
                <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" x-on:click="hidePanel()">Cancelar</button>
                <button type="submit" form="barber-invitation-create" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-5 py-2.5 text-sm font-semibold text-[#17130b] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Crear cuenta e invitar</button>
            </x-slot:footer>
        </x-form-slide-over>

        <x-form-slide-over
            id="create-barber-profile"
            title="Crear perfil para miembro"
            description="Selecciona un miembro activo de esta barbería."
            :open="old('form_context') === 'create-barber-profile'"
        >
            <form id="barber-profile-create" method="POST" action="{{ route('tenant.barbers.store', $barbershop) }}" class="space-y-5">
                @csrf
                <input type="hidden" name="form_context" value="create-barber-profile">
                <div>
                    <label for="user_id" class="mb-2 block text-sm font-semibold text-slate-200">Miembro</label>
                    <select id="user_id" name="user_id" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                        <option value="">Selecciona un miembro activo</option>
                        @foreach ($eligibleMemberships as $membership)
                            <option value="{{ $membership->user_id }}" @selected(old('form_context') === 'create-barber-profile' && (string) old('user_id') === (string) $membership->user_id)>{{ $membership->user->name }} · {{ $membership->user->email }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="display_name" class="mb-2 block text-sm font-semibold text-slate-200">Nombre para agenda</label>
                    <input id="display_name" name="display_name" type="text" value="{{ old('form_context') === 'create-barber-profile' ? old('display_name') : '' }}" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" placeholder="Ej. Ana Cortes">
                </div>
            </form>
            <x-slot:footer>
                <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" x-on:click="hidePanel()">Cancelar</button>
                <button type="submit" form="barber-profile-create" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-5 py-2.5 text-sm font-semibold text-[#17130b] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Guardar barbero</button>
            </x-slot:footer>
        </x-form-slide-over>
    </div>
</x-tenant-layout>
