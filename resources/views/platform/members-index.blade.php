<x-platform-layout title="Miembros · BarberHub" active-page="members">
    <div class="mx-auto max-w-[1440px] space-y-6">
        <header>
            <h1 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">Miembros de las barberías</h1>
            <p class="mt-2 text-sm leading-6 text-slate-400">Consulta dueños, administradores, barberos y clientes. Esta vista es de solo lectura.</p>
        </header>

        <x-ui.surface as="section" class="p-5 sm:p-6">
            <form method="GET" action="{{ route('platform.members.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="w-full sm:max-w-md">
                    <label for="barbershop" class="block text-sm font-semibold text-slate-200">Barbería</label>
                    <select id="barbershop" name="barbershop" class="mt-2 w-full rounded-lg border-slate-700 bg-slate-900 text-sm text-slate-100 focus:border-amber-300 focus:ring-amber-300">
                        <option value="">Todas las barberías</option>
                        @foreach ($barbershops as $barbershop)
                            <option value="{{ $barbershop->slug }}" @selected($selectedBarbershop?->id === $barbershop->id)>{{ $barbershop->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-bold text-slate-950 hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-100">Filtrar</button>
            </form>
        </x-ui.surface>

        <x-ui.surface as="section" class="overflow-hidden">
            <header class="border-b border-slate-800 px-5 py-5 sm:px-6">
                <h2 class="text-lg font-bold text-white">Equipo · {{ $memberships->total() }}</h2>
                <p class="mt-1 text-sm text-slate-400">Dueños, administradores y barberos, incluidas las membresías inactivas.</p>
            </header>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800 text-left text-sm">
                    <thead class="bg-slate-900 text-slate-400">
                        <tr>
                            <th scope="col" class="px-5 py-4">Nombre</th>
                            <th scope="col" class="px-5 py-4">Correo</th>
                            <th scope="col" class="px-5 py-4">Barbería</th>
                            <th scope="col" class="px-5 py-4">Rol</th>
                            <th scope="col" class="px-5 py-4">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800 text-slate-300">
                        @forelse ($memberships as $membership)
                            <tr>
                                <td class="px-5 py-4 font-semibold text-slate-100">{{ $membership->user->name }}</td>
                                <td class="px-5 py-4">{{ $membership->user->email }}</td>
                                <td class="px-5 py-4">{{ $membership->barbershop->name }}</td>
                                <td class="px-5 py-4">{{ $roleLabels[$membership->role] ?? $membership->role }}</td>
                                <td class="px-5 py-4">{{ $membership->status === \App\Models\Membership::STATUS_ACTIVE ? 'Activo' : 'Inactivo' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">No hay miembros del equipo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($memberships->hasPages())
                <div class="border-t border-slate-800 p-5">{{ $memberships->links() }}</div>
            @endif
        </x-ui.surface>

        <x-ui.surface as="section" class="overflow-hidden">
            <header class="border-b border-slate-800 px-5 py-5 sm:px-6">
                <h2 class="text-lg font-bold text-white">Clientes · {{ $customers->total() }}</h2>
                <p class="mt-1 text-sm text-slate-400">Incluye clientes que todavía no tienen una cuenta vinculada.</p>
            </header>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800 text-left text-sm">
                    <thead class="bg-slate-900 text-slate-400">
                        <tr>
                            <th scope="col" class="px-5 py-4">Nombre</th>
                            <th scope="col" class="px-5 py-4">Correo</th>
                            <th scope="col" class="px-5 py-4">Teléfono</th>
                            <th scope="col" class="px-5 py-4">Barbería</th>
                            <th scope="col" class="px-5 py-4">Cuenta</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800 text-slate-300">
                        @forelse ($customers as $customer)
                            <tr>
                                <td class="px-5 py-4 font-semibold text-slate-100">{{ $customer->name }}</td>
                                <td class="px-5 py-4">{{ $customer->email ?? 'Sin correo' }}</td>
                                <td class="px-5 py-4">{{ $customer->phone ?? 'Sin teléfono' }}</td>
                                <td class="px-5 py-4">{{ $customer->barbershop->name }}</td>
                                <td class="px-5 py-4">{{ $customer->user_id ? 'Vinculada' : 'Sin cuenta' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">No hay clientes registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($customers->hasPages())
                <div class="border-t border-slate-800 p-5">{{ $customers->links() }}</div>
            @endif
        </x-ui.surface>
    </div>
</x-platform-layout>
