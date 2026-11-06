<x-report-layout
    :barbershop="$barbershop"
    :role-label="$roleLabel"
    title="Servicios más realizados"
    description="Compara los servicios por citas completadas. Este reporte no muestra precios ni ingresos."
>
    <div class="space-y-6">
        <p class="text-sm text-slate-300">
            Del {{ $reportData->from()->format('d/m/Y') }} al {{ $reportData->to()->format('d/m/Y') }}
            <span class="text-slate-500">·</span>
            Zona horaria: {{ $reportData->timezone() }}
        </p>

        <x-report-filters
            :action="route('tenant.reports.services', $barbershop)"
            :from="$reportData->from()"
            :to="$reportData->to()"
            :barbers="$barberOptions"
            :services="$serviceOptions"
            :barber-id="$barberId"
            :service-id="$serviceId"
        />

        @if ($completedTotal > 0)
            <section class="space-y-4" aria-label="Servicios por citas completadas">
                @foreach ($serviceRows as $service)
                    <article class="rounded-xl border border-slate-800 bg-[#111820] p-5">
                        <div class="flex flex-wrap items-baseline justify-between gap-3">
                            <h2 class="font-semibold text-slate-100">{{ $service['name'] }}</h2>
                            <p class="text-sm tabular-nums text-slate-300">{{ number_format($service['completed']) }} citas · {{ number_format($service['share'], 1) }}%</p>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-800" role="img" aria-label="{{ number_format($service['share'], 1) }}% de las citas completadas">
                            <div class="h-full rounded-full bg-amber-300" style="width: {{ $service['share'] }}%"></div>
                        </div>
                    </article>
                @endforeach
            </section>
        @else
            <section class="flex min-h-56 flex-col items-center justify-center gap-3 rounded-xl border border-slate-800 bg-[#111820] px-6 py-10 text-center" aria-label="Sin servicios completados">
                <h2 class="text-lg font-semibold text-slate-100">No hay servicios completados en este periodo</h2>
                <p class="max-w-lg text-sm leading-6 text-slate-400">Ajusta el periodo o los filtros para consultar otras citas de {{ $barbershop->name }}.</p>
            </section>
        @endif
    </div>
</x-report-layout>
