<x-platform-layout title="Contención de seguridad · BarberHub" active-page="security">
    <div class="mx-auto max-w-[1440px] space-y-6">
        <header>
            <h1 class="text-3xl font-bold tracking-[-0.035em] text-white sm:text-4xl">Contención de seguridad</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">Revoca sesiones de cuentas con un motivo documentado o suspende un módulo de una barbería durante 15 minutos. Las restricciones vigentes no se prolongan al repetir la solicitud.</p>
        </header>

        <section class="space-y-4 rounded-xl border border-slate-800 bg-[#11171d] p-5 sm:p-6">
            <div>
                <h2 class="text-lg font-bold text-white">Revocar sesiones de una cuenta</h2>
                <p class="mt-1 text-sm text-slate-400">Se cerrarán sus sesiones activas, se invalidarán sus suscripciones push y se rotará su token de acceso recordado.</p>
            </div>
            <div class="divide-y divide-slate-800">
                @foreach ($users as $account)
                    <form method="POST" action="{{ route('platform.security.sessions.revoke', $account) }}" class="grid gap-3 py-4 sm:grid-cols-[minmax(0,1fr)_minmax(16rem,2fr)_auto] sm:items-end">
                        @csrf
                        <div>
                            <p class="font-semibold text-slate-100">{{ $account->name }}</p>
                            <p class="text-xs text-slate-400">{{ $account->email }}</p>
                        </div>
                        <div>
                            <label for="session-reason-{{ $account->id }}" class="mb-1 block text-xs font-medium text-slate-300">Motivo requerido</label>
                            <input id="session-reason-{{ $account->id }}" name="reason" required maxlength="1000" class="w-full rounded-md border-slate-700 bg-[#0c1116] text-sm text-white focus:border-amber-300 focus:ring-amber-300" value="{{ old('reason') }}">
                            @error('reason')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="rounded-lg border border-rose-500/40 px-3 py-2 text-xs font-bold text-rose-200 transition hover:border-rose-300 hover:bg-rose-500/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-300">Revocar sesiones</button>
                    </form>
                @endforeach
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-slate-800 bg-[#11171d] p-5 sm:p-6">
            <div>
                <h2 class="text-lg font-bold text-white">Suspender o liberar un módulo</h2>
                <p class="mt-1 text-sm text-slate-400">Solo se bloquea el módulo seleccionado en esa barbería. El panel, las herramientas de sesión y los demás módulos permanecen disponibles.</p>
            </div>
            @foreach ($barbershops as $barbershop)
                <article class="rounded-lg border border-slate-800 bg-[#0c1116] p-4">
                    <h3 class="font-semibold text-white">{{ $barbershop->name }} <span class="font-normal text-slate-500">({{ $barbershop->slug }})</span></h3>
                    <div class="mt-4 grid gap-4 xl:grid-cols-2">
                        @foreach ($modules as $module => $label)
                            @php($containment = $containments->get($barbershop->id.'-'.$module))
                            @php($isSuspended = $containment !== null && $containment->released_at === null && $containment->expires_at->isFuture())
                            <div class="rounded-md border border-slate-800 p-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="text-sm font-semibold text-slate-200">{{ $label }}</h4>
                                    @if ($isSuspended)
                                        <span class="text-xs font-medium text-amber-200">Suspendido hasta {{ $containment->expires_at->format('d/m/Y H:i:s') }}</span>
                                    @else
                                        <span class="text-xs text-emerald-300">Disponible</span>
                                    @endif
                                </div>
                                @if ($isSuspended)
                                    <form method="POST" action="{{ route('platform.security.modules.release', $barbershop) }}" class="mt-3 flex flex-col gap-2 sm:flex-row">
                                        @csrf
                                        <input type="hidden" name="module" value="{{ $module }}">
                                        <label for="release-reason-{{ $barbershop->id }}-{{ $module }}" class="sr-only">Motivo para levantar {{ $label }}</label>
                                        <input id="release-reason-{{ $barbershop->id }}-{{ $module }}" name="reason" required maxlength="1000" placeholder="Motivo para levantar" class="min-w-0 flex-1 rounded-md border-slate-700 bg-[#11171d] text-sm text-white focus:border-amber-300 focus:ring-amber-300">
                                        <button type="submit" class="rounded-lg border border-emerald-500/40 px-3 py-2 text-xs font-bold text-emerald-200 transition hover:border-emerald-300 hover:bg-emerald-500/10">Levantar restricción</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('platform.security.modules.suspend', $barbershop) }}" class="mt-3 flex flex-col gap-2 sm:flex-row">
                                        @csrf
                                        <input type="hidden" name="module" value="{{ $module }}">
                                        <label for="containment-reason-{{ $barbershop->id }}-{{ $module }}" class="sr-only">Motivo para suspender {{ $label }}</label>
                                        <input id="containment-reason-{{ $barbershop->id }}-{{ $module }}" name="reason" required maxlength="1000" placeholder="Motivo requerido" class="min-w-0 flex-1 rounded-md border-slate-700 bg-[#11171d] text-sm text-white focus:border-amber-300 focus:ring-amber-300">
                                        <button type="submit" class="rounded-lg border border-amber-300/40 px-3 py-2 text-xs font-bold text-amber-100 transition hover:border-amber-200 hover:bg-amber-300/10">Suspender 15 min</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </section>
    </div>
</x-platform-layout>
