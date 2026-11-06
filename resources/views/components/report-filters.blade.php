@props(['action', 'from', 'to', 'barbers', 'services', 'barberId' => null, 'serviceId' => null])

<form method="GET" action="{{ $action }}" class="rounded-xl border border-slate-800 bg-[#111820] p-5" aria-label="Filtros del reporte">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="space-y-2">
            <label for="report-from" class="text-sm font-medium text-slate-200">Desde</label>
            <input id="report-from" name="from" type="date" value="{{ $from->format('Y-m-d') }}" required class="min-h-11 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 text-sm text-white focus:border-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-300/40">
        </div>
        <div class="space-y-2">
            <label for="report-to" class="text-sm font-medium text-slate-200">Hasta</label>
            <input id="report-to" name="to" type="date" value="{{ $to->format('Y-m-d') }}" required class="min-h-11 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 text-sm text-white focus:border-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-300/40">
        </div>
        <div class="space-y-2">
            <label for="report-barber" class="text-sm font-medium text-slate-200">Barbero</label>
            <select id="report-barber" name="barber_id" class="min-h-11 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 text-sm text-white focus:border-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-300/40">
                <option value="">Todos los barberos</option>
                @foreach ($barbers as $barber)
                    <option value="{{ $barber->id }}" @selected((string) $barberId === (string) $barber->id)>{{ $barber->display_name }}{{ $barber->active ? '' : ' (inactivo)' }}</option>
                @endforeach
            </select>
        </div>
        <div class="space-y-2">
            <label for="report-service" class="text-sm font-medium text-slate-200">Servicio</label>
            <select id="report-service" name="service_id" class="min-h-11 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 text-sm text-white focus:border-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-300/40">
                <option value="">Todos los servicios</option>
                @foreach ($services as $service)
                    <option value="{{ $service->id }}" @selected((string) $serviceId === (string) $service->id)>{{ $service->name }}{{ $service->active ? '' : ' (inactivo)' }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs leading-5 text-slate-400">El periodo puede incluir hasta 366 días. Las citas se agrupan por fecha y hora local.</p>
        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-4 py-2 text-sm font-semibold text-[#17130b] hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 focus-visible:ring-offset-[#111820]">Aplicar filtros</button>
    </div>
</form>
