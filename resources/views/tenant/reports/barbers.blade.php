<x-report-layout
    :barbershop="$barbershop"
    :role-label="$roleLabel"
    title="Actividad por barbero"
    description="Consulta las citas por estado y el tiempo programado en citas completadas durante el periodo."
>
    <div class="space-y-6">
        <p class="text-sm text-slate-300">
            Del {{ $reportData->from()->format('d/m/Y') }} al {{ $reportData->to()->format('d/m/Y') }}
            <span class="text-slate-500">·</span>
            Zona horaria: {{ $reportData->timezone() }}
        </p>

        <x-report-filters
            :action="route('tenant.reports.barbers', $barbershop)"
            :from="$reportData->from()"
            :to="$reportData->to()"
            :barbers="$barberOptions"
            :services="$serviceOptions"
            :barber-id="$barberId"
            :service-id="$serviceId"
        />

        @if (array_sum($totals) > 0)
            <section class="space-y-4" aria-label="Actividad del equipo">
                <div class="overflow-x-auto rounded-xl border border-slate-800">
                    <table class="min-w-full divide-y divide-slate-800 text-left text-sm">
                        <caption class="sr-only">Citas por estado y minutos programados para cada barbero</caption>
                        <thead class="bg-[#111820] text-xs uppercase tracking-wide text-slate-400">
                            <tr>
                                <th scope="col" class="px-4 py-3">Barbero</th>
                                <th scope="col" class="px-4 py-3">Completadas</th>
                                <th scope="col" class="px-4 py-3">Canceladas</th>
                                <th scope="col" class="px-4 py-3">No asistió</th>
                                <th scope="col" class="px-4 py-3">Programadas</th>
                                <th scope="col" class="px-4 py-3">Minutos programados*</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800 bg-[#0b1117] text-slate-200">
                            @foreach ($barberRows as $barber)
                                <tr>
                                    <th scope="row" class="whitespace-nowrap px-4 py-3 font-medium">{{ $barber['name'] }}</th>
                                    <td class="px-4 py-3 tabular-nums">{{ number_format($barber['completed']) }}</td>
                                    <td class="px-4 py-3 tabular-nums">{{ number_format($barber['cancelled']) }}</td>
                                    <td class="px-4 py-3 tabular-nums">{{ number_format($barber['noShow']) }}</td>
                                    <td class="px-4 py-3 tabular-nums">{{ number_format($barber['scheduled']) }}</td>
                                    <td class="px-4 py-3 tabular-nums">{{ number_format($barber['bookedMinutes'], 1) }} min</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-slate-700 bg-[#111820] font-semibold text-white">
                            <tr>
                                <th scope="row" class="px-4 py-3">Total del periodo</th>
                                <td class="px-4 py-3 tabular-nums">{{ number_format($totals['completed']) }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ number_format($totals['cancelled']) }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ number_format($totals['noShow']) }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ number_format($totals['scheduled']) }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ number_format($totals['bookedMinutes'], 1) }} min</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="text-xs leading-5 text-slate-400">* Duración programada de citas completadas, no tiempo real trabajado. Los horarios pueden variar entre barberos; no es una clasificación de productividad.</p>
            </section>
        @else
            <section class="flex min-h-56 flex-col items-center justify-center gap-3 rounded-xl border border-slate-800 bg-[#111820] px-6 py-10 text-center" aria-label="Sin actividad de barberos">
                <h2 class="text-lg font-semibold text-slate-100">No hay actividad de barberos en este periodo</h2>
                <p class="max-w-lg text-sm leading-6 text-slate-400">Ajusta el periodo o los filtros para consultar otras citas de {{ $barbershop->name }}.</p>
            </section>
        @endif
    </div>
</x-report-layout>
