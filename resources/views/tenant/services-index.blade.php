<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" active-page="services" :can-manage-members="$canManageMembers">
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Servicios de {{ $barbershop->name }}</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-400">Administra el catálogo, precio, duración y disponibilidad operativa de cada servicio.</p>
            </div>
            <button
                type="button"
                class="inline-flex min-h-11 w-fit items-center justify-center rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-semibold text-[#17130b] transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300"
                data-slide-over-trigger
                x-on:click="$dispatch('form-slide-over-open', { id: 'create-service' })"
            >
                Crear servicio
            </button>
        </header>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-[#111820]" aria-labelledby="services-heading">
            <header class="flex flex-col gap-2 border-b border-slate-800 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h2 id="services-heading" class="font-semibold text-white">Catálogo de servicios</h2>
                    <p class="mt-1 text-sm text-slate-400">El estado activo controla qué servicios podrán ofrecerse en agenda.</p>
                </div>
                <span class="inline-flex w-fit items-center rounded-lg border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-slate-300">
                    {{ $services->where('active', true)->count() }} {{ $services->where('active', true)->count() === 1 ? 'servicio activo' : 'servicios activos' }}
                </span>
            </header>

            @if ($services->isNotEmpty())
                <div class="hidden grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_8rem_8rem_8rem_auto] gap-4 border-b border-slate-800 px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400 xl:grid">
                    <span>Servicio</span>
                    <span>Descripción</span>
                    <span>Precio</span>
                    <span>Duración</span>
                    <span>Estado</span>
                    <span class="text-right">Acciones</span>
                </div>
                <div class="divide-y divide-slate-800">
                    @foreach ($services as $service)
                        <article class="grid gap-4 px-5 py-5 sm:px-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_8rem_8rem_8rem_auto] xl:items-center">
                            <div>
                                <p class="font-semibold text-slate-100">{{ $service->name }}</p>
                                <p class="text-sm text-slate-400 xl:hidden">{{ $service->description ?: 'Sin descripción' }}</p>
                            </div>
                            <p class="hidden text-sm text-slate-300 xl:block">{{ $service->description ?: 'Sin descripción' }}</p>
                            <p class="text-sm text-slate-200">${{ number_format((float) $service->price, 2) }} <span class="text-slate-500 xl:hidden">· precio</span></p>
                            <p class="text-sm text-slate-200">{{ $service->duration_minutes }} min</p>
                            <p class="text-sm {{ $service->active ? 'text-emerald-200' : 'text-slate-400' }}">{{ $service->active ? 'Activo' : 'Inactivo' }}</p>
                            <button
                                type="button"
                                class="inline-flex min-h-10 w-full items-center justify-center rounded-lg border border-slate-600 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:border-slate-500 hover:bg-slate-800 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 xl:w-auto"
                                data-slide-over-trigger
                                x-on:click="$dispatch('form-slide-over-open', { id: 'service-{{ $service->id }}' })"
                            >
                                Editar
                            </button>
                        </article>

                        <x-form-slide-over
                            id="service-{{ $service->id }}"
                            title="Editar servicio"
                            description="Actualiza los datos del servicio."
                            :open="old('form_context') === 'service-'.$service->id"
                        >
                            <form id="service-update-{{ $service->id }}" method="POST" action="{{ route('tenant.services.update', [$barbershop, $service]) }}" class="space-y-5">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="form_context" value="service-{{ $service->id }}">

                                <div>
                                    <label for="name-{{ $service->id }}" class="mb-2 block text-sm font-semibold text-slate-200">Nombre</label>
                                    <input id="name-{{ $service->id }}" name="name" type="text" value="{{ old('form_context') === 'service-'.$service->id ? old('name', $service->name) : $service->name }}" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                                </div>
                                <div>
                                    <label for="description-{{ $service->id }}" class="mb-2 block text-sm font-semibold text-slate-200">Descripción</label>
                                    <input id="description-{{ $service->id }}" name="description" type="text" value="{{ old('form_context') === 'service-'.$service->id ? old('description', $service->description) : $service->description }}" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" placeholder="Opcional">
                                </div>
                                <div>
                                    <label for="price-{{ $service->id }}" class="mb-2 block text-sm font-semibold text-slate-200">Precio</label>
                                    <input id="price-{{ $service->id }}" name="price" type="number" min="0" step="0.01" value="{{ old('form_context') === 'service-'.$service->id ? old('price', $service->price) : $service->price }}" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" @if ($errors->has('price')) aria-invalid="true" @endif>
                                </div>
                                <div>
                                    <label for="duration-{{ $service->id }}" class="mb-2 block text-sm font-semibold text-slate-200">Duración (minutos)</label>
                                    <input id="duration-{{ $service->id }}" name="duration_minutes" type="number" min="1" max="1440" step="1" value="{{ old('form_context') === 'service-'.$service->id ? old('duration_minutes', $service->duration_minutes) : $service->duration_minutes }}" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                                </div>
                                <div>
                                    <label for="active-{{ $service->id }}" class="mb-2 block text-sm font-semibold text-slate-200">Estado</label>
                                    <select id="active-{{ $service->id }}" name="active" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                                        <option value="1" @selected(old('form_context') === 'service-'.$service->id ? old('active', $service->active) : $service->active)>Activo</option>
                                        <option value="0" @selected(old('form_context') === 'service-'.$service->id ? ! old('active', $service->active) : ! $service->active)>Inactivo</option>
                                    </select>
                                </div>
                            </form>

                            <x-slot:footer>
                                <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" x-on:click="hidePanel()">Cancelar</button>
                                <button type="submit" form="service-update-{{ $service->id }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-5 py-2.5 text-sm font-semibold text-[#17130b] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Guardar cambios</button>
                            </x-slot:footer>
                        </x-form-slide-over>
                    @endforeach
                </div>
            @else
                <div class="px-5 py-14 text-center sm:px-6">
                    <h3 class="font-semibold text-white">Todavía no hay servicios</h3>
                    <p class="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-400">Crea el primer servicio para preparar el catálogo comercial de la barbería.</p>
                    <button type="button" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-semibold text-[#17130b]" data-slide-over-trigger x-on:click="$dispatch('form-slide-over-open', { id: 'create-service' })">Crear servicio</button>
                </div>
            @endif
        </section>

        <x-form-slide-over id="create-service" title="Crear servicio" description="Los datos se guardan dentro de esta barbería." :open="old('form_context') === 'create-service'">
            <form id="service-create" method="POST" action="{{ route('tenant.services.store', $barbershop) }}" class="space-y-5">
                @csrf
                <input type="hidden" name="form_context" value="create-service">
                <div>
                    <label for="name" class="mb-2 block text-sm font-semibold text-slate-200">Nombre</label>
                    <input id="name" name="name" type="text" value="{{ old('form_context') === 'create-service' ? old('name') : '' }}" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" placeholder="Ej. Corte clásico">
                </div>
                <div>
                    <label for="description" class="mb-2 block text-sm font-semibold text-slate-200">Descripción</label>
                    <input id="description" name="description" type="text" value="{{ old('form_context') === 'create-service' ? old('description') : '' }}" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" placeholder="Opcional">
                </div>
                <div>
                    <label for="price" class="mb-2 block text-sm font-semibold text-slate-200">Precio</label>
                    <input id="price" name="price" type="number" min="0" step="0.01" value="{{ old('form_context') === 'create-service' ? old('price') : '' }}" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                </div>
                <div>
                    <label for="duration_minutes" class="mb-2 block text-sm font-semibold text-slate-200">Duración (minutos)</label>
                    <input id="duration_minutes" name="duration_minutes" type="number" min="1" max="1440" step="1" value="{{ old('form_context') === 'create-service' ? old('duration_minutes') : '' }}" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2.5 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                </div>
            </form>
            <x-slot:footer>
                <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" x-on:click="hidePanel()">Cancelar</button>
                <button type="submit" form="service-create" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-5 py-2.5 text-sm font-semibold text-[#17130b] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Guardar servicio</button>
            </x-slot:footer>
        </x-form-slide-over>
    </div>
</x-tenant-layout>
