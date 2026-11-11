<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" active-page="barber-services" :can-manage-members="$canManageMembers">
    <div class="mx-auto max-w-3xl space-y-6">
        <header>
            <h1 class="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Servicios de {{ $barber->display_name }}</h1>
            <p class="mt-1 text-sm leading-6 text-slate-400">Selecciona los servicios que este barbero puede ofrecer.</p>
        </header>

        <form method="POST" action="{{ $manager ? route('tenant.barber-services.update', [$barbershop, $barber]) : route('tenant.barber-services.self.update', $barbershop) }}" class="space-y-5 rounded-xl border border-slate-800 bg-[#111820] p-5 sm:p-6">
            @csrf
            @method('PUT')
            <fieldset class="space-y-3">
                <legend class="mb-4 font-semibold text-white">Catálogo de servicios</legend>
                @forelse ($services as $service)
                    <label for="service-{{ $service->id }}" class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-700 p-4 hover:bg-slate-800/60">
                        <input id="service-{{ $service->id }}" name="service_ids[]" type="checkbox" value="{{ $service->id }}" @checked(in_array($service->id, old('service_ids', $barber->services->modelKeys()))) class="mt-1 rounded border-slate-600 bg-slate-900 text-amber-300 focus:ring-amber-300">
                        <span class="min-w-0">
                            <span class="block font-semibold text-slate-100">{{ $service->name }}</span>
                            <span class="block text-sm text-slate-400">{{ $service->description ?: 'Sin descripción' }} · ${{ number_format((float) $service->price, 2) }} · {{ $service->duration_minutes }} min</span>
                            @unless ($service->active)
                                <span class="mt-1 block text-xs text-amber-200">Servicio inactivo</span>
                            @endunless
                        </span>
                    </label>
                @empty
                    <p class="rounded-lg border border-slate-700 p-4 text-sm text-slate-400">Todavía no hay servicios en esta barbería.</p>
                @endforelse
            </fieldset>

            <div class="flex flex-wrap justify-end gap-3 border-t border-slate-800 pt-5">
                <a href="{{ $manager ? route('tenant.barbers.index', $barbershop) : route('tenant.dashboard', $barbershop) }}" class="inline-flex min-h-11 items-center rounded-lg border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-200">Cancelar</a>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-5 py-2.5 text-sm font-semibold text-[#17130b] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Guardar servicios</button>
            </div>
        </form>
    </div>
</x-tenant-layout>
