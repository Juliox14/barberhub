<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" active-page="customers" :can-manage-members="$canManageMembers">
    <div class="mx-auto max-w-7xl space-y-6">
        <header>
            <h1 class="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Clientes de {{ $barbershop->name }}</h1>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">Consulta los datos de contacto y el estado de registro de tus clientes. Invita a quienes todavía no tienen una cuenta vinculada.</p>
        </header>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-[#111820]" aria-labelledby="customers-heading">
            <header class="border-b border-slate-800 px-5 py-5 sm:px-6">
                <h2 id="customers-heading" class="font-semibold text-white">Directorio de clientes</h2>
                <p class="mt-1 text-sm text-slate-400">{{ $customers->total() }} {{ $customers->total() === 1 ? 'cliente' : 'clientes' }}</p>
            </header>

            @if ($customers->isNotEmpty())
                <div class="hidden grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,0.8fr)_8rem_5rem] gap-4 border-b border-slate-800 px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400 lg:grid">
                    <x-sortable-grid-heading column="name" label="Nombre" :sort="$sortState['sort']" :direction="$sortState['direction']" page-parameter="customers_page" />
                    <x-sortable-grid-heading column="email" label="Correo electrónico" :sort="$sortState['sort']" :direction="$sortState['direction']" page-parameter="customers_page" />
                    <x-sortable-grid-heading column="phone" label="Teléfono" :sort="$sortState['sort']" :direction="$sortState['direction']" page-parameter="customers_page" />
                    <span>Registro</span>
                    <span class="text-right">Acciones</span>
                </div>
                <div class="divide-y divide-slate-800">
                    @foreach ($customers as $customer)
                        <article class="grid gap-3 px-5 py-4 sm:px-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,0.8fr)_8rem_5rem] lg:items-center lg:gap-4">
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-100">{{ $customer->name }}</p>
                                <p class="text-xs text-slate-500 lg:hidden">Nombre</p>
                            </div>
                            <div class="min-w-0">
                                <p class="break-words text-sm text-slate-300">{{ $customer->email ?: 'Sin correo electrónico' }}</p>
                                <p class="text-xs text-slate-500 lg:hidden">Correo electrónico</p>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm text-slate-300">{{ $customer->phone ?: 'Sin teléfono' }}</p>
                                <p class="text-xs text-slate-500 lg:hidden">Teléfono</p>
                            </div>
                            <div>
                                @if ($customer->user_id !== null)
                                    <span class="inline-flex rounded-full bg-emerald-400/10 px-2.5 py-1 text-xs font-semibold text-emerald-200">Registrado</span>
                                @else
                                    <span class="inline-flex rounded-full bg-slate-800 px-2.5 py-1 text-xs font-semibold text-slate-300">Sin registrar</span>
                                @endif
                            </div>
                            <div class="lg:text-right">
                                @if ($customer->user_id === null)
                                    <button type="button" x-on:click="$dispatch('open-modal', 'invite-customer-{{ $customer->id }}')" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-amber-300/40 px-3 py-2 text-sm font-semibold text-amber-200 hover:bg-amber-300/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" aria-label="Invitar a {{ $customer->name }}">Invitar</button>
                                    @include('tenant.partials.customer-invitation-modal', ['customer' => $customer])
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($customers->hasPages())
                    <div class="border-t border-slate-800 p-5">{{ $customers->links() }}</div>
                @endif
            @else
                <div class="px-5 py-14 text-center sm:px-6">
                    <h3 class="font-semibold text-white">Todavía no hay clientes.</h3>
                    <p class="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-400">Los clientes aparecerán aquí cuando se agreguen a la barbería, tengan o no una cuenta.</p>
                </div>
            @endif
        </section>
    </div>
</x-tenant-layout>
