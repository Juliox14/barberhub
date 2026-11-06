<x-report-layout
    :barbershop="$barbershop"
    :role-label="$roleLabel"
    title="Evolución de atenciones"
    description="Consulta la evolución de citas completadas y compárala con el periodo calendario anterior de igual duración."
>
    <div class="space-y-6">
        <p class="text-sm text-slate-300">
            Del {{ $reportData->from()->format('d/m/Y') }} al {{ $reportData->to()->format('d/m/Y') }}
            <span class="text-slate-500">·</span>
            Zona horaria: {{ $reportData->timezone() }}
        </p>

        <x-report-filters
            :action="route('tenant.reports.evolution', $barbershop)"
            :from="$reportData->from()"
            :to="$reportData->to()"
            :barbers="$barbers"
            :services="$services"
            :barber-id="$barberId"
            :service-id="$serviceId"
        />

        <form method="GET" action="{{ route('tenant.reports.evolution', $barbershop) }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-800 bg-[#111820] p-5">
            <input type="hidden" name="from" value="{{ $reportData->from()->toDateString() }}">
            <input type="hidden" name="to" value="{{ $reportData->to()->toDateString() }}">
            @if ($barberId)<input type="hidden" name="barber_id" value="{{ $barberId }}">@endif
            @if ($serviceId)<input type="hidden" name="service_id" value="{{ $serviceId }}">@endif
            <label for="evolution-grouping" class="space-y-2 text-sm font-medium text-slate-200">
                <span>Agrupar por</span>
                <select id="evolution-grouping" name="grouping" class="block min-h-11 rounded-lg border border-slate-700 bg-slate-900 px-3 text-white">
                    <option value="day" @selected($grouping === 'day')>Día</option>
                    <option value="week" @selected($grouping === 'week')>Semana</option>
                    <option value="month" @selected($grouping === 'month')>Mes</option>
                </select>
            </label>
            <button type="submit" class="min-h-11 rounded-lg bg-amber-300 px-4 py-2 text-sm font-semibold text-[#17130b]">Actualizar agrupación</button>
        </form>

        <section class="grid gap-4 sm:grid-cols-2" aria-label="Comparación de atenciones completadas">
            <x-report-stat label="Atenciones del periodo" :value="$comparison['currentTotal']" description="Citas marcadas como completadas." />
            <x-report-stat label="Periodo anterior" :value="$comparison['previousTotal']" description="Del {{ \Carbon\CarbonImmutable::parse($comparison['previousFrom'])->format('d/m/Y') }} al {{ \Carbon\CarbonImmutable::parse($comparison['previousTo'])->format('d/m/Y') }}." />
        </section>
        <p class="rounded-xl border border-slate-800 bg-[#111820] p-5 text-sm text-slate-300">
            Diferencia: <strong class="text-white">{{ $comparison['difference'] > 0 ? '+' : '' }}{{ $comparison['difference'] }}</strong> atenciones
            ({{ $comparison['percentageDifference'] === null ? 'sin base de comparación' : ($comparison['percentageDifference'] > 0 ? '+' : '').$comparison['percentageDifference'].'%' }}).
            @if ($comparison['percentageDifference'] === null) El periodo anterior no tuvo atenciones completadas; no se calcula un porcentaje. @endif
        </p>

        <div class="overflow-x-auto rounded-xl border border-slate-800 bg-[#111820]">
            <table class="w-full min-w-[32rem] text-left text-sm">
                <caption class="px-5 py-4 text-left font-semibold text-slate-100">Atenciones completadas por {{ ['day' => 'día', 'week' => 'semana', 'month' => 'mes'][$grouping] }}</caption>
                <thead class="border-y border-slate-800 text-xs uppercase tracking-wide text-slate-400"><tr><th scope="col" class="px-5 py-3">Periodo</th><th scope="col" class="px-5 py-3 text-right">Completadas</th></tr></thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($evolution as $bucket)
                        <tr><th scope="row" class="px-5 py-3 font-medium text-slate-200">{{ $bucket['label'] }}</th><td class="px-5 py-3 text-right text-white">{{ $bucket['count'] }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="px-5 py-8 text-center text-slate-400">No hay atenciones en este periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-report-layout>
