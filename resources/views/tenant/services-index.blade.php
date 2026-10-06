<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" active-page="services" :can-manage-members="$canManageMembers">
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Servicios de {{ $barbershop->name }}</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">Administra el catálogo, precio, duración y disponibilidad operativa de cada servicio.</p>
            </div>
            <div class="inline-flex w-fit items-center gap-2 rounded-lg border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-300">
                {{ $services->where('active', true)->count() }} {{ $services->where('active', true)->count() === 1 ? 'servicio activo' : 'servicios activos' }}
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
                <h2 class="font-semibold text-white">Crear servicio</h2>
                <p class="mt-1 text-sm text-slate-400">Los datos se guardan siempre dentro de esta barbería; no se acepta un tenant desde el navegador.</p>
            </div>
            <form method="POST" action="{{ route('tenant.services.store', $barbershop) }}" class="grid gap-4 px-5 py-5 sm:px-6 lg:grid-cols-[minmax(0,1fr)_minmax(110px,0.35fr)_minmax(130px,0.35fr)_auto] lg:items-end">
                @csrf
                <div class="grid gap-4 md:grid-cols-2 lg:col-span-1">
                    <div>
                        <label for="name" class="mb-2 block text-sm font-medium text-slate-200">Nombre</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white" placeholder="Ej. Corte clásico">
                    </div>
                    <div>
                        <label for="description" class="mb-2 block text-sm font-medium text-slate-200">Descripción</label>
                        <input id="description" name="description" type="text" value="{{ old('description') }}" class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white" placeholder="Opcional">
                    </div>
                </div>
                <div>
                    <label for="price" class="mb-2 block text-sm font-medium text-slate-200">Precio</label>
                    <input id="price" name="price" type="number" min="0" step="0.01" value="{{ old('price') }}" required class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white">
                </div>
                <div>
                    <label for="duration_minutes" class="mb-2 block text-sm font-medium text-slate-200">Duración</label>
                    <input id="duration_minutes" name="duration_minutes" type="number" min="1" max="1440" step="1" value="{{ old('duration_minutes') }}" required class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white" placeholder="Minutos">
                </div>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-semibold text-[#17130b]">Guardar servicio</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-[#111820]">
            <div class="border-b border-slate-800 px-5 py-5 sm:px-6">
                <h2 class="font-semibold text-white">Catálogo de servicios</h2>
                <p class="mt-1 text-sm text-slate-400">El estado activo prepara qué servicios podrán ofrecerse cuando se implemente agenda.</p>
            </div>
            <div class="divide-y divide-slate-800">
                @forelse ($services as $service)
                    <form method="POST" action="{{ route('tenant.services.update', [$barbershop, $service]) }}" class="grid gap-4 px-5 py-5 sm:px-6 xl:grid-cols-[minmax(180px,0.9fr)_minmax(220px,1.2fr)_minmax(110px,0.35fr)_minmax(130px,0.4fr)_minmax(130px,0.35fr)_auto] xl:items-center">
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="name-{{ $service->id }}" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500 xl:sr-only">Nombre</label>
                            <input id="name-{{ $service->id }}" name="name" type="text" value="{{ $service->name }}" required class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white">
                        </div>
                        <div>
                            <label for="description-{{ $service->id }}" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500 xl:sr-only">Descripción</label>
                            <input id="description-{{ $service->id }}" name="description" type="text" value="{{ $service->description }}" class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white" placeholder="Opcional">
                        </div>
                        <div>
                            <label for="price-{{ $service->id }}" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500 xl:sr-only">Precio</label>
                            <input id="price-{{ $service->id }}" name="price" type="number" min="0" step="0.01" value="{{ $service->price }}" required class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white">
                        </div>
                        <div>
                            <label for="duration-{{ $service->id }}" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500 xl:sr-only">Duración</label>
                            <input id="duration-{{ $service->id }}" name="duration_minutes" type="number" min="1" max="1440" step="1" value="{{ $service->duration_minutes }}" required class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white">
                        </div>
                        <div>
                            <label for="active-{{ $service->id }}" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-slate-500 xl:sr-only">Estado</label>
                            <select id="active-{{ $service->id }}" name="active" class="block w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100">
                                <option value="1" @selected($service->active)>Activo</option>
                                <option value="0" @selected(! $service->active)>Inactivo</option>
                            </select>
                        </div>
                        <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-slate-600 px-3.5 py-2.5 text-sm font-semibold text-slate-200">Actualizar</button>
                    </form>
                @empty
                    <div class="px-5 py-14 text-center sm:px-6">
                        <h3 class="font-semibold text-white">Todavía no hay servicios</h3>
                        <p class="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-400">Crea el primer servicio para preparar el catálogo comercial de la barbería.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-tenant-layout>
