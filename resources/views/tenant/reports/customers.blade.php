<x-report-layout
    :barbershop="$barbershop"
    :role-label="$roleLabel"
    title="Clientes nuevos y recurrentes"
    description="Personas únicas con al menos una cita completada en el periodo; se cuenta su primera visita completada a esta barbería."
>
    <div class="space-y-6">
        <p class="text-sm text-slate-300">
            Del {{ $reportData->from()->format('d/m/Y') }} al {{ $reportData->to()->format('d/m/Y') }}
            <span class="text-slate-500">·</span>
            Zona horaria: {{ $reportData->timezone() }}
        </p>

        <x-report-filters
            :action="route('tenant.reports.customers', $barbershop)"
            :from="$reportData->from()"
            :to="$reportData->to()"
            :barbers="$barberOptions"
            :services="$serviceOptions"
            :barber-id="$barberId"
            :service-id="$serviceId"
        />

        @if ($customerSummary['unique'] > 0)
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Clientes atendidos">
                <x-report-stat label="Clientes atendidos" :value="$customerSummary['unique']" description="Clientes identificados únicos con cita completada, sin duplicar visitas." />
                <x-report-stat label="Nuevos" :value="$customerSummary['new']" description="Primera cita completada en esta barbería dentro del periodo." />
                <x-report-stat label="Recurrentes" :value="$customerSummary['returning']" description="Con una cita completada anterior al inicio del periodo." />
            </section>
        @else
            <section class="flex min-h-56 flex-col items-center justify-center gap-3 rounded-xl border border-slate-800 bg-[#111820] px-6 py-10 text-center" aria-label="Sin clientes atendidos">
                <h2 class="text-lg font-semibold text-slate-100">No hay clientes identificados atendidos en este periodo</h2>
                <p class="max-w-lg text-sm leading-6 text-slate-400">Ajusta el periodo o los filtros para consultar otras citas de {{ $barbershop->name }}.</p>
            </section>
        @endif

        <p class="rounded-xl border border-slate-800 bg-[#111820] p-4 text-sm leading-6 text-slate-300">
            Citas completadas sin cliente identificado (sin customer_id):
            <strong class="tabular-nums text-white">{{ number_format($customerSummary['unidentifiedAppointments']) }}</strong>.
            Un cliente sin cuenta de usuario sí se considera identificado si tiene registro de cliente.
        </p>
    </div>
</x-report-layout>
