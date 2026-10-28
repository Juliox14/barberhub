<section class="mb-6 rounded-xl border border-slate-800 bg-[#11171d] p-5 sm:p-6" aria-labelledby="security-alerts-title">
    <h2 id="security-alerts-title" class="text-lg font-bold text-white">Alertas de seguridad</h2>
    <p class="mt-2 text-sm text-slate-400">Últimos 20 grupos. Cada grupo conserva el total de ocurrencias y la última acción; la bitácora inferior conserva los eventos individuales. El correo se limita a uno por grupo cada 15 minutos.</p>
    <p class="mt-2 text-xs text-slate-400">
        Responsable: {{ config('security.alert_recipient') ?: 'sin configurar (solo alertas internas)' }}.
        Configurá SECURITY_ALERT_RECIPIENT y un transporte de correo real para recibir avisos externos.
        @if (in_array(config('mail.default'), ['log', 'array'], true))
            El transporte actual es {{ config('mail.default') }}: no entrega correo externo.
        @endif
    </p>
    <div class="mt-4 divide-y divide-slate-800">
        @forelse ($securityAlerts as $alert)
            <article class="space-y-1 py-3 text-sm text-slate-300">
                <p class="font-semibold text-amber-100">{{ \App\Notifications\SecurityIncidentAlert::TITLES[$alert->event] ?? $alert->event }}</p>
                @if ($alert->subject_id !== null)
                    <p>{{ $alert->account_name ?? 'Cuenta eliminada' }} · {{ $alert->account_email ?? 'sin correo disponible' }} · Cuenta #{{ $alert->subject_id }}</p>
                @else
                    <p>{{ $alert->shop_name ?? 'Barbería eliminada' }} · Barbería #{{ $alert->barbershop_id }} · Módulo: {{ $alert->module }}</p>
                @endif
                <p class="break-words">{{ $alert->reason }}</p>
                <p class="text-xs text-slate-400">{{ $alert->occurrences }} {{ $alert->occurrences === 1 ? 'ocurrencia' : 'ocurrencias' }} · Última: {{ $alert->updated_at }}</p>
            </article>
        @empty
            <p class="py-3 text-sm text-slate-400">Todavía no hay alertas de contención.</p>
        @endforelse
    </div>
</section>
