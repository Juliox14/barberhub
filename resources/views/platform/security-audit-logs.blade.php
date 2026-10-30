<x-platform-layout title="Bitácora de seguridad · BarberHub" active-page="security-audit-logs">
    <div class="mx-auto max-w-[1440px] space-y-6">
        <header>
            <h1 class="text-3xl font-bold tracking-[-0.035em] text-white sm:text-4xl">Bitácora de seguridad</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-400">Historial de accesos y cambios administrativos relevantes de la plataforma.</p>
        </header>

        <div data-audit-poller data-endpoint="{{ route('platform.security-audit-logs.index', ['page' => $auditLogs->currentPage()], false) }}">
            <div data-audit-results>
                @include('platform.partials.security-audit-results')
            </div>
            <p data-audit-status class="mt-2 min-h-5 text-xs text-slate-400" role="status" aria-live="polite" aria-atomic="true"></p>
        </div>
    </div>
</x-platform-layout>
