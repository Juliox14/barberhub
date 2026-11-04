<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" active-page="customers" :can-manage-members="$canManageMembers">
    <div class="mx-auto max-w-7xl space-y-6">
        <header>
            <h1 class="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Clientes de {{ $barbershop->name }}</h1>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">Consulta los datos de contacto de los clientes registrados en esta barbería.</p>
        </header>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-[#111820]" aria-labelledby="customers-heading">
            <header class="border-b border-slate-800 px-5 py-5 sm:px-6">
                <h2 id="customers-heading" class="font-semibold text-white">Directorio de clientes</h2>
                <p class="mt-1 text-sm text-slate-400">{{ $customers->total() }} {{ $customers->total() === 1 ? 'cliente registrado' : 'clientes registrados' }}</p>
            </header>

            @if ($customers->isNotEmpty())
                <div class="hidden grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)] gap-4 border-b border-slate-800 px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400 sm:grid">
                    <span>Nombre</span>
                    <span>Correo electrónico</span>
                    <span>Teléfono</span>
                </div>
                <div class="divide-y divide-slate-800">
                    @foreach ($customers as $customer)
                        <article class="grid gap-2 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)] sm:gap-4 sm:px-6">
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-100">{{ $customer->name }}</p>
                                <p class="text-xs text-slate-500 sm:hidden">Nombre</p>
                            </div>
                            <div class="min-w-0">
                                <p class="break-words text-sm text-slate-300">{{ $customer->email ?: 'Sin correo electrónico' }}</p>
                                <p class="text-xs text-slate-500 sm:hidden">Correo electrónico</p>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm text-slate-300">{{ $customer->phone ?: 'Sin teléfono' }}</p>
                                <p class="text-xs text-slate-500 sm:hidden">Teléfono</p>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($customers->hasPages())
                    <div class="border-t border-slate-800 p-5">{{ $customers->links() }}</div>
                @endif
            @else
                <div class="px-5 py-14 text-center sm:px-6">
                    <h3 class="font-semibold text-white">Todavía no hay clientes registrados.</h3>
                    <p class="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-400">Los clientes de esta barbería aparecerán aquí cuando se registren.</p>
                </div>
            @endif
        </section>
    </div>
</x-tenant-layout>
