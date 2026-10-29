@include('platform.partials.security-alerts')

<details open data-security-section="audit-log" class="overflow-hidden rounded-xl border border-slate-800 bg-[#11171d]">
        <summary class="cursor-pointer px-5 py-5 text-lg font-bold text-white outline-none hover:text-amber-100 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-300 sm:px-6">Eventos registrados</summary>
        <div class="flex flex-wrap items-center justify-between gap-3 border-y border-slate-800 px-5 py-4 sm:px-6">
            <p class="text-sm text-slate-400">{{ $auditLogs->total() }} {{ $auditLogs->total() === 1 ? 'registro disponible' : 'registros disponibles' }}.</p>
            <span class="inline-flex w-fit items-center gap-2 rounded-full bg-slate-800 px-3 py-1.5 text-xs font-medium text-slate-300">Solo lectura</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800 text-left text-sm">
                <thead class="bg-[#0c1116] text-xs uppercase tracking-[0.08em] text-slate-500">
                    <tr>
                        <th class="px-5 py-4 font-semibold sm:px-6">Fecha</th>
                        <th class="px-5 py-4 font-semibold">Evento</th>
                        <th class="px-5 py-4 font-semibold">Actor</th>
                        <th class="px-5 py-4 font-semibold">Sujeto</th>
                        <th class="px-5 py-4 font-semibold">Barbería del evento</th>
                        <th class="px-5 py-4 font-semibold">Detalles y origen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($auditLogs as $auditLog)
                        <tr class="align-top transition hover:bg-slate-800/40">
                            <td class="whitespace-nowrap px-5 py-4 text-slate-300 sm:px-6">{{ $auditLog->created_at->format('d/m/Y H:i:s') }}</td>
                            <td class="px-5 py-4 font-mono text-xs text-amber-100">{{ $auditLog->event }}</td>
                            <td class="px-5 py-4 text-slate-300">
                                @include('platform.partials.security-audit-account', ['account' => $auditLog->actor, 'fallback' => $auditLog->actor_id === null ? 'Invitado' : 'Cuenta eliminada (#'.$auditLog->actor_id.')'])
                            </td>
                            <td class="px-5 py-4 text-slate-300">
                                @if ($auditLog->hasUserSubject())
                                    @include('platform.partials.security-audit-account', ['account' => $auditLog->subjectUser, 'fallback' => $auditLog->subject_id === null ? '—' : 'Cuenta eliminada (#'.$auditLog->subject_id.')'])
                                @elseif (str_starts_with($auditLog->event, 'platform.barbershop.'))
                                    @if ($auditLog->subject_id === null)
                                        —
                                    @elseif ($auditLog->subjectBarbershop)
                                        <p>{{ $auditLog->subjectBarbershop->name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Barbería #{{ $auditLog->subject_id }}</p>
                                    @else
                                        <p>{{ $auditLog->metadata['name'] ?? 'Barbería eliminada' }} (eliminada)</p>
                                        <p class="mt-1 text-xs text-slate-500">Barbería #{{ $auditLog->subject_id }}</p>
                                    @endif
                                @else
                                    {{ $auditLog->subject_id === null ? '—' : 'Sujeto #'.$auditLog->subject_id }}
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-300">
                                @if ($auditLog->eventBarbershop)
                                    <p>{{ $auditLog->eventBarbershop->name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Barbería #{{ $auditLog->barbershop_id }}</p>
                                @elseif (str_starts_with($auditLog->event, 'login.') || in_array($auditLog->event, ['logout', 'password.updated', 'password.reset', 'session.transfer_confirmed'], true))
                                    Sin barbería asociada al evento
                                @elseif ($auditLog->hasShopSubject() && $auditLog->subjectBarbershop)
                                    <p>{{ $auditLog->subjectBarbershop->name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Barbería #{{ $auditLog->subject_id }}</p>
                                @elseif ($auditLog->hasShopSubject() && $auditLog->subject_id !== null)
                                    <p>{{ $auditLog->metadata['name'] ?? 'Barbería eliminada' }} (eliminada)</p>
                                    <p class="mt-1 text-xs text-slate-500">Barbería #{{ $auditLog->subject_id }}</p>
                                @elseif ($auditLog->barbershop_id !== null)
                                    <p>{{ $auditLog->metadata['name'] ?? 'Barbería eliminada' }} (eliminada)</p>
                                    <p class="mt-1 text-xs text-slate-500">Barbería #{{ $auditLog->barbershop_id }}</p>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="max-w-xl px-5 py-4 text-xs text-slate-400">
                                @if ($auditLog->metadata !== [])
                                    <pre class="whitespace-pre-wrap break-words font-mono">{{ json_encode($auditLog->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                @endif
                                <p class="mt-2 break-all">IP: {{ $auditLog->ip_address ?? '—' }}</p>
                                <p class="mt-1 break-words">Navegador: {{ $auditLog->user_agent ?? '—' }}</p>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-14 text-center text-sm text-slate-400 sm:px-6">Todavía no hay eventos de seguridad registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($auditLogs->hasPages())
            <nav class="flex flex-col gap-3 border-t border-slate-800 px-5 py-4 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-6" aria-label="Paginación de la bitácora">
                <p class="text-slate-400">Mostrando {{ $auditLogs->firstItem() }}–{{ $auditLogs->lastItem() }} de {{ $auditLogs->total() }}</p>
                <div class="flex gap-2">
                    @if ($auditLogs->onFirstPage())<span class="rounded-lg border border-slate-800 px-3 py-2 text-slate-600">Anterior</span>@else<a href="{{ $auditLogs->previousPageUrl() }}" class="rounded-lg border border-slate-700 px-3 py-2 font-medium text-slate-200 transition hover:border-amber-300/50 hover:text-amber-100">Anterior</a>@endif
                    @if ($auditLogs->hasMorePages())<a href="{{ $auditLogs->nextPageUrl() }}" class="rounded-lg border border-slate-700 px-3 py-2 font-medium text-slate-200 transition hover:border-amber-300/50 hover:text-amber-100">Siguiente</a>@else<span class="rounded-lg border border-slate-800 px-3 py-2 text-slate-600">Siguiente</span>@endif
                </div>
            </nav>
        @endif
</details>
