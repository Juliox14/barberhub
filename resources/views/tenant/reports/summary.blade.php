<x-report-layout
    :barbershop="$barbershop"
    :role-label="$roleLabel"
    title="Resumen de citas"
    description="Consulta el total de citas y su estado durante el periodo seleccionado."
>
    <div class="space-y-6">
        <p class="text-sm text-slate-300">
            Del {{ $reportData->from()->format('d/m/Y') }} al {{ $reportData->to()->format('d/m/Y') }}
            <span class="text-slate-500">·</span>
            Zona horaria: {{ $reportData->timezone() }}
        </p>

        <x-report-filters
            :action="route('tenant.reports.summary', $barbershop)"
            :from="$reportData->from()"
            :to="$reportData->to()"
            :barbers="$barbers"
            :services="$services"
            :barber-id="$barberId"
            :service-id="$serviceId"
        />

        @if ($summary['total'] > 0)
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5" aria-label="Citas por estado">
                <x-report-stat label="Total de citas" :value="$summary['total']" description="Citas que comienzan en el periodo." />
                <x-report-stat label="Completadas" :value="$summary['completed']" description="Marcadas como completadas." />
                <x-report-stat label="Canceladas" :value="$summary['cancelled']" description="Citas del periodo que fueron canceladas." />
                <x-report-stat label="No asistió" :value="$summary['no_show']" description="Citas marcadas como inasistencia." />
                <x-report-stat label="Programadas" :value="$summary['scheduled']" description="Citas actualmente programadas." />
            </section>
        @else
            <section class="flex min-h-56 flex-col items-center justify-center gap-3 rounded-xl border border-slate-800 bg-[#111820] px-6 py-10 text-center" aria-label="Sin citas">
                <h2 class="text-lg font-semibold text-slate-100">No hay citas en este periodo</h2>
                <p class="max-w-lg text-sm leading-6 text-slate-400">Ajusta el periodo o los filtros para consultar otras citas de {{ $barbershop->name }}.</p>
            </section>
        @endif
    </div>
</x-report-layout>
