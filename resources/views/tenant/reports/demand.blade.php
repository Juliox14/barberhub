<x-report-layout
    :barbershop="$barbershop"
    :role-label="$roleLabel"
    title="Demanda por día y horario"
    description="Distribución de las reservas según el día de la semana y la hora local de inicio."
>
    <div class="space-y-6">
        <p class="text-sm text-slate-300">
            Del {{ $reportData->from()->format('d/m/Y') }} al {{ $reportData->to()->format('d/m/Y') }}
            <span class="text-slate-500">·</span>
            Zona horaria: {{ $reportData->timezone() }}
        </p>

        <x-report-filters
            :action="route('tenant.reports.demand', $barbershop)"
            :from="$reportData->from()"
            :to="$reportData->to()"
            :barbers="$barbers"
            :services="$services"
            :barber-id="$barberId"
            :service-id="$serviceId"
        />

        <p class="rounded-xl border border-amber-300/30 bg-amber-300/5 p-4 text-sm leading-6 text-amber-100">
            Reservas (todos los estados): {{ $demandTotal }}. Esta distribución describe citas atendidas o reservadas; no representa demanda no atendida ni mide ocupación. Las horas corresponden a la hora local de inicio.
        </p>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#111820]">
                <table class="w-full min-w-[28rem] text-left text-sm">
                    <caption class="px-5 py-4 text-left font-semibold text-slate-100">Reservas por día de semana</caption>
                    <thead class="border-y border-slate-800 text-xs uppercase tracking-wide text-slate-400"><tr><th scope="col" class="px-5 py-3">Día</th><th scope="col" class="px-5 py-3">Proporción</th><th scope="col" class="px-5 py-3 text-right">Reservas</th></tr></thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($weekdayLabels as $day => $label)
                            @php($percentage = $demandTotal === 0 ? 0 : round(($weekdayCounts[$day] / $demandTotal) * 100))
                            <tr>
                                <th scope="row" class="whitespace-nowrap px-5 py-3 font-medium text-slate-200">{{ $label }}</th>
                                <td class="w-full px-5 py-3"><div class="h-2 min-w-24 overflow-hidden rounded-full bg-slate-800" role="img" aria-label="{{ $percentage }}%"><div class="h-full rounded-full bg-amber-300" style="width: {{ $percentage }}%"></div></div></td>
                                <td class="px-5 py-3 text-right text-white">{{ $weekdayCounts[$day] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#111820]">
                <table class="w-full min-w-[28rem] text-left text-sm">
                    <caption class="px-5 py-4 text-left font-semibold text-slate-100">Reservas por hora de inicio local</caption>
                    <thead class="border-y border-slate-800 text-xs uppercase tracking-wide text-slate-400"><tr><th scope="col" class="px-5 py-3">Hora</th><th scope="col" class="px-5 py-3">Proporción</th><th scope="col" class="px-5 py-3 text-right">Reservas</th></tr></thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($hourCounts as $hour => $count)
                            @php($percentage = $demandTotal === 0 ? 0 : round(($count / $demandTotal) * 100))
                            <tr>
                                <th scope="row" class="whitespace-nowrap px-5 py-3 font-medium text-slate-200">{{ sprintf('%02d:00', $hour) }}</th>
                                <td class="w-full px-5 py-3"><div class="h-2 min-w-24 overflow-hidden rounded-full bg-slate-800" role="img" aria-label="{{ $percentage }}%"><div class="h-full rounded-full bg-amber-300" style="width: {{ $percentage }}%"></div></div></td>
                                <td class="px-5 py-3 text-right text-white">{{ $count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-report-layout>
