<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" active-page="members" :can-manage-members="$canManageMembers">
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Equipo y accesos</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">Administra quién puede acceder a {{ $barbershop->name }} y sus permisos.</p>
            </div>
            <button
                type="button"
                class="inline-flex min-h-11 w-fit shrink-0 items-center justify-center gap-2 rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-semibold text-[#17130b] transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 focus-visible:ring-offset-[#0a0e12]"
                data-slide-over-trigger
                x-on:click="$dispatch('form-slide-over-open', { id: 'invite-member' })"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14m7-7H5"/></svg>
                Invitar miembro
            </button>
        </div>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-[#111820]" aria-labelledby="team-members-heading">
            <header class="flex flex-col gap-2 border-b border-slate-800 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h2 id="team-members-heading" class="font-semibold text-white">Miembros del equipo</h2>
                    <p class="mt-1 text-sm text-slate-400">Gestiona los roles y el acceso de cada persona.</p>
                </div>
                <span class="inline-flex w-fit items-center gap-2 rounded-lg border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-300">
                    <svg class="h-4 w-4 text-amber-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    {{ $memberships->count() }} {{ $memberships->count() === 1 ? 'miembro' : 'miembros' }}
                </span>
            </header>

            @if ($memberships->isNotEmpty())
                <div class="hidden grid-cols-[minmax(0,1.5fr)_minmax(9rem,0.65fr)_minmax(9rem,0.75fr)_auto] gap-4 border-b border-slate-800 px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400 lg:grid">
                    <span>Miembro</span>
                    <span>Rol</span>
                    <span>Acceso</span>
                    <span class="text-right">Acciones</span>
                </div>
                <div class="divide-y divide-slate-800">
                    @foreach ($memberships as $membership)
                        @php($isCurrentUser = Auth::id() === $membership->user_id)
                        <article class="grid gap-4 px-5 py-5 sm:px-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(9rem,0.65fr)_minmax(9rem,0.75fr)_auto] lg:items-center">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-800 text-sm font-bold text-amber-200" aria-hidden="true">{{ str($membership->user->name)->substr(0, 1)->upper() }}</div>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-slate-100">
                                        {{ $membership->user->name }}
                                        @if ($isCurrentUser)
                                            <span class="ml-1 rounded-full border border-slate-600 bg-slate-800 px-2 py-0.5 text-xs font-medium text-slate-300">Tú</span>
                                        @endif
                                    </p>
                                    <p class="truncate text-sm text-slate-400">{{ $membership->user->email }}</p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-3 lg:block">
                                <span class="text-xs font-medium text-slate-500 lg:hidden">Rol</span>
                                <span class="inline-flex rounded-lg border border-slate-700 px-3 py-2 text-sm font-medium text-slate-200">{{ $roleLabels[$membership->role] ?? $membership->role }}</span>
                            </div>

                            <div class="flex items-center justify-between gap-3 lg:block">
                                <span class="text-xs font-medium text-slate-500 lg:hidden">Acceso</span>
                                <span @class([
                                    'inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium',
                                    'border-emerald-400/25 bg-emerald-400/10 text-emerald-200' => $membership->status === \App\Models\Membership::STATUS_ACTIVE,
                                    'border-amber-300/25 bg-amber-300/10 text-amber-100' => $membership->status !== \App\Models\Membership::STATUS_ACTIVE,
                                ])>
                                    <span @class([
                                        'h-2 w-2 rounded-full',
                                        'bg-emerald-400' => $membership->status === \App\Models\Membership::STATUS_ACTIVE,
                                        'bg-amber-300' => $membership->status !== \App\Models\Membership::STATUS_ACTIVE,
                                    ])></span>
                                    {{ $statusLabels[$membership->status] ?? $membership->status }}
                                </span>
                            </div>

                            <div class="flex justify-end">
                                <button
                                    type="button"
                                    class="inline-flex min-h-10 w-full items-center justify-center rounded-lg border border-slate-600 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:border-slate-500 hover:bg-slate-800 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 lg:w-auto"
                                    data-slide-over-trigger
                                    x-on:click="$dispatch('form-slide-over-open', { id: 'member-{{ $membership->id }}' })"
                                >
                                    Administrar
                                </button>
                            </div>
                        </article>

                        <x-form-slide-over
                            id="member-{{ $membership->id }}"
                            title="Administrar miembro"
                            description="Gestiona el rol y el acceso de esta persona."
                            :open="old('form_context') === 'member-'.$membership->id"
                        >
                            <div class="flex items-center gap-3 border-b border-slate-800 pb-6">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-slate-800 text-base font-bold text-amber-200" aria-hidden="true">{{ str($membership->user->name)->substr(0, 1)->upper() }}</div>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-white">{{ $membership->user->name }}</p>
                                    <p class="truncate text-sm text-slate-400">{{ $membership->user->email }}</p>
                                </div>
                            </div>

                            <form id="membership-update-{{ $membership->id }}" method="POST" action="{{ route('tenant.members.update', [$barbershop, $membership]) }}" class="space-y-5 pt-6">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="form_context" value="member-{{ $membership->id }}">

                                <div>
                                    <label for="role-{{ $membership->id }}" class="mb-2 block text-sm font-semibold text-slate-200">Rol</label>
                                    <select id="role-{{ $membership->id }}" name="role" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100 transition focus:border-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-300/25" @if ($errors->has('role')) aria-invalid="true" @endif>
                                        @foreach ($roleLabels as $role => $label)
                                            <option value="{{ $role }}" @selected(old('role', $membership->role) === $role)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('role')
                                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="status-{{ $membership->id }}" class="mb-2 block text-sm font-semibold text-slate-200">Acceso</label>
                                    <select id="status-{{ $membership->id }}" name="status" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100 transition focus:border-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-300/25" @if ($errors->has('status')) aria-invalid="true" @endif>
                                        @foreach ($statusLabels as $status => $label)
                                            <option value="{{ $status }}" @selected(old('status', $membership->status) === $status)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('status')
                                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                                    @enderror
                                    @error('owner')
                                        <p class="mt-2 text-sm text-rose-300" role="alert">{{ $message }}</p>
                                    @enderror
                                </div>
                            </form>

                            <section class="mt-8 border-t border-slate-800 pt-6" aria-labelledby="membership-danger-{{ $membership->id }}">
                                <h3 id="membership-danger-{{ $membership->id }}" class="font-semibold text-rose-300">Zona de peligro</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-400">Al eliminar esta membresía, la persona perderá el acceso a {{ $barbershop->name }}.</p>
                                <form method="POST" action="{{ route('tenant.members.destroy', [$barbershop, $membership]) }}" onsubmit="return confirm('¿Eliminar esta membresía?');" class="mt-4">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="form_context" value="member-{{ $membership->id }}">
                                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-rose-400/35 px-3.5 py-2 text-sm font-semibold text-rose-200 transition hover:bg-rose-400/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-300">Eliminar membresía</button>
                                </form>
                            </section>

                            <x-slot:footer>
                                <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" x-on:click="hidePanel()">Cancelar</button>
                                <button type="submit" form="membership-update-{{ $membership->id }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-5 py-2.5 text-sm font-semibold text-[#17130b] transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Guardar cambios</button>
                            </x-slot:footer>
                        </x-form-slide-over>
                    @endforeach
                </div>
            @else
                <div class="px-5 py-14 text-center sm:px-6">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-800 text-slate-400"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                    <h3 class="mt-4 font-semibold text-white">Todavía no hay miembros registrados</h3>
                    <p class="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-400">Invita a la primera persona para que forme parte del equipo de esta barbería.</p>
                    <button type="button" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-semibold text-[#17130b] transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" data-slide-over-trigger x-on:click="$dispatch('form-slide-over-open', { id: 'invite-member' })">Invitar miembro</button>
                </div>
            @endif
        </section>

        <x-form-slide-over
            id="invite-member"
            title="Invitar miembro"
            description="Crea la cuenta y el perfil de agenda del nuevo barbero."
            :open="old('form_context') === 'invite-member'"
        >
            <p class="mb-6 text-sm leading-6 text-slate-400">La persona recibirá un enlace para verificar su correo y configurar su contraseña antes de iniciar sesión.</p>
            <form id="member-invitation" method="POST" action="{{ route('tenant.barbers.invitations.store', $barbershop) }}" class="space-y-5">
                @csrf
                <input type="hidden" name="form_context" value="invite-member">

                <div>
                    <label for="account_name" class="mb-2 block text-sm font-semibold text-slate-200">Nombre completo</label>
                    <input id="account_name" name="account_name" type="text" value="{{ old('account_name') }}" required autocomplete="name" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" @if ($errors->has('account_name')) aria-invalid="true" @endif>
                    @error('account_name')
                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="account_email" class="mb-2 block text-sm font-semibold text-slate-200">Correo electrónico</label>
                    <input id="account_email" name="account_email" type="email" value="{{ old('account_email') }}" required autocomplete="email" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" @if ($errors->has('account_email')) aria-invalid="true" @endif>
                    @error('account_email')
                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="agenda_name" class="mb-2 block text-sm font-semibold text-slate-200">Nombre para la agenda</label>
                    <input id="agenda_name" name="agenda_name" type="text" value="{{ old('agenda_name') }}" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" @if ($errors->has('agenda_name')) aria-invalid="true" @endif placeholder="Ej. Ana Cortes">
                    @error('agenda_name')
                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
            </form>

            <x-slot:footer>
                <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" x-on:click="hidePanel()">Cancelar</button>
                <button type="submit" form="member-invitation" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-5 py-2.5 text-sm font-semibold text-[#17130b] transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Crear cuenta e invitar</button>
            </x-slot:footer>
        </x-form-slide-over>
    </div>
</x-tenant-layout>
