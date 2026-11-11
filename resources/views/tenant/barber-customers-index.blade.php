<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" active-page="customers" :can-manage-members="$canManageMembers">
    <div class="mx-auto max-w-7xl space-y-6">
        <header>
            <h1 class="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Mis clientes habituales</h1>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">Aquí verás a los clientes que te elijan como su barbero preferido en {{ $barbershop->name }}.</p>
        </header>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-[#111820]" aria-labelledby="regular-customers-heading">
            <header class="border-b border-slate-800 px-5 py-5 sm:px-6">
                <h2 id="regular-customers-heading" class="font-semibold text-white">Clientes habituales</h2>
            </header>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-800 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <tr>
                            <th scope="col" class="px-5 py-3 sm:px-6">Nombre</th>
                            <th scope="col" class="px-5 py-3 sm:px-6">Correo electrónico</th>
                            <th scope="col" class="px-5 py-3 sm:px-6">Teléfono</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($customers as $customer)
                            <tr>
                                <td class="px-5 py-4 text-slate-100 sm:px-6">{{ $customer->name ?: 'Sin nombre' }}</td>
                                <td class="break-words px-5 py-4 text-slate-300 sm:px-6">{{ $customer->email ?: 'Sin correo electrónico' }}</td>
                                <td class="px-5 py-4 text-slate-300 sm:px-6">{{ $customer->phone ?: 'Sin teléfono' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-5 py-14 text-center sm:px-6">
                                    <h3 class="font-semibold text-white">Todavía no tienes clientes habituales.</h3>
                                    <p class="mx-auto mt-2 max-w-lg leading-6 text-slate-400">Los clientes aparecerán aquí cuando indiquen que te prefieren en sus preferencias de esta barbería.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($customers instanceof \Illuminate\Pagination\LengthAwarePaginator && $customers->hasPages())
                <div class="border-t border-slate-800 p-5">{{ $customers->links() }}</div>
            @endif
        </section>
    </div>
</x-tenant-layout>
