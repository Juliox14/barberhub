<x-platform-layout title="Gestionar barberías · BarberHub" active-page="barbershops">
    <div class="mx-auto max-w-[1440px] space-y-6">
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <h1 class="text-3xl font-bold tracking-[-0.035em] text-white sm:text-4xl">Gestionar barberías</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-400">Consulta las barberías registradas en BarberHub y su estado actual dentro de la plataforma.</p>
            </div>
            <a href="{{ route('platform.barbershops.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-amber-300 px-4 py-3 text-sm font-bold text-[#1a1409] shadow-[0_10px_24px_rgba(251,191,36,0.13)] transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-100 focus-visible:ring-offset-2 focus-visible:ring-offset-[#0a0e12]">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                Nueva barbería
            </a>
        </header>

        @if (session('status'))
            <div class="flex items-start gap-3 rounded-xl border border-emerald-400/25 bg-emerald-400/10 px-4 py-4 text-sm text-emerald-100" role="status">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="m8 12 2.5 2.5L16 9"/></svg>
                {{ session('status') }}
            </div>
        @endif

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-[#11171d]">
            <div class="flex flex-col justify-between gap-3 border-b border-slate-800 px-5 py-5 sm:flex-row sm:items-center sm:px-6">
                <div>
                    <h2 class="text-lg font-bold text-white">Barberías registradas</h2>
                    <p class="mt-1 text-sm text-slate-400">{{ $barbershops->total() }} {{ $barbershops->total() === 1 ? 'registro disponible' : 'registros disponibles' }}.</p>
                </div>
                <span class="inline-flex w-fit items-center gap-2 rounded-full bg-slate-800 px-3 py-1.5 text-xs font-medium text-slate-300"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>Datos de plataforma</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800 text-left text-sm">
                    <thead class="bg-[#0c1116] text-xs uppercase tracking-[0.08em] text-slate-500">
                        <tr>
                            <th class="px-5 py-4 font-semibold sm:px-6">Barbería</th>
                            <th class="px-5 py-4 font-semibold">Slug</th>
                            <th class="px-5 py-4 font-semibold">Zona horaria</th>
                            <th class="px-5 py-4 font-semibold">Estado</th>
                            <th class="px-5 py-4 text-right font-semibold">Miembros</th>
                            <th class="px-5 py-4 text-right font-semibold sm:px-6">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($barbershops as $barbershop)
                            <tr class="transition hover:bg-slate-800/40">
                                <td class="px-5 py-4 sm:px-6"><p class="font-semibold text-slate-100">{{ $barbershop->name }}</p><p class="mt-0.5 text-xs text-slate-500">Registrada el {{ $barbershop->created_at->format('d/m/Y') }}</p></td>
                                <td class="px-5 py-4 font-mono text-xs text-slate-400">{{ $barbershop->slug }}</td>
                                <td class="px-5 py-4 text-slate-300">{{ $barbershop->timezone }}</td>
                                <td class="px-5 py-4"><span @class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-emerald-400/10 text-emerald-300' => $barbershop->status === \App\Models\Barbershop::STATUS_ACTIVE, 'bg-rose-400/10 text-rose-300' => $barbershop->status !== \App\Models\Barbershop::STATUS_ACTIVE])><span @class(['h-1.5 w-1.5 rounded-full', 'bg-emerald-400' => $barbershop->status === \App\Models\Barbershop::STATUS_ACTIVE, 'bg-rose-400' => $barbershop->status !== \App\Models\Barbershop::STATUS_ACTIVE])></span>{{ $barbershop->status === \App\Models\Barbershop::STATUS_ACTIVE ? 'Activa' : 'Inactiva' }}</span></td>
                                <td class="px-5 py-4 text-right font-medium text-slate-200">{{ $barbershop->memberships_count }}</td>
                                <td class="px-5 py-4 sm:px-6">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('platform.barbershops.edit', $barbershop) }}" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-200 transition hover:border-amber-300/50 hover:text-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Editar</a>
                                        <form method="POST" action="{{ route('platform.barbershops.destroy', $barbershop) }}" onsubmit="return confirm('¿Eliminar esta barbería? Esta acción no se puede deshacer.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-rose-500/40 px-3 py-2 text-xs font-bold text-rose-200 transition hover:border-rose-300 hover:bg-rose-500/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-300">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-14 text-center text-sm text-slate-400 sm:px-6">Todavía no hay barberías registradas. Crea la primera para comenzar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($barbershops->hasPages())
                <nav class="flex flex-col gap-3 border-t border-slate-800 px-5 py-4 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-6" aria-label="Paginación de barberías">
                    <p class="text-slate-400">Mostrando {{ $barbershops->firstItem() }}–{{ $barbershops->lastItem() }} de {{ $barbershops->total() }}</p>
                    <div class="flex gap-2">
                        @if ($barbershops->onFirstPage())<span class="rounded-lg border border-slate-800 px-3 py-2 text-slate-600">Anterior</span>@else<a href="{{ $barbershops->previousPageUrl() }}" class="rounded-lg border border-slate-700 px-3 py-2 font-medium text-slate-200 transition hover:border-amber-300/50 hover:text-amber-100">Anterior</a>@endif
                        @if ($barbershops->hasMorePages())<a href="{{ $barbershops->nextPageUrl() }}" class="rounded-lg border border-slate-700 px-3 py-2 font-medium text-slate-200 transition hover:border-amber-300/50 hover:text-amber-100">Siguiente</a>@else<span class="rounded-lg border border-slate-800 px-3 py-2 text-slate-600">Siguiente</span>@endif
                    </div>
                </nav>
            @endif
        </section>
    </div>
</x-platform-layout>
