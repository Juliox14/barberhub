<x-account-security-layout title="Autenticación en dos pasos">
    <div class="mx-auto max-w-7xl space-y-6">
        <header>
            <h1 class="text-3xl font-bold tracking-[-0.035em] text-white sm:text-4xl">Autenticación en dos pasos</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">{{ $required ? 'Esta medida es obligatoria para tu tipo de cuenta.' : 'Puedes activar esta protección opcionalmente.' }}</p>
            <p class="mt-3 text-sm font-medium text-slate-300">Estado: <span class="text-amber-200">{{ $enabled ? 'Activada' : ($pending ? 'Pendiente de confirmación' : 'Desactivada') }}</span></p>
        </header>

        @if (isset($qrCode, $manualSecret))
            <section class="space-y-5 rounded-xl border border-slate-800 bg-[#11171d] p-5 sm:p-6" aria-label="Configuración de la aplicación autenticadora">
                <div>
                    <h2 class="text-lg font-bold text-white">Vincula tu aplicación autenticadora</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-400">Escanea este código con tu aplicación autenticadora o introduce la clave manualmente.</p>
                </div>
                <div class="w-fit max-w-full overflow-x-auto rounded-md bg-white p-3">{!! $qrCode !!}</div>
                <p class="max-w-3xl break-words text-sm leading-6 text-slate-300">Clave manual: <code class="select-all break-all rounded bg-[#0c1116] px-2 py-1 text-amber-200">{{ $manualSecret }}</code></p>
                <form method="POST" action="{{ route('two-factor.confirm') }}" class="max-w-2xl space-y-4">
                    @csrf
                    <div>
                        <label for="code" class="mb-1 block text-sm font-medium text-slate-300">Código de seis dígitos</label>
                        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required maxlength="6" class="w-full rounded-md border-slate-700 bg-[#0c1116] text-sm text-white focus:border-amber-300 focus:ring-amber-300">
                        <x-input-error :messages="$errors->get('code')" class="mt-2 !text-rose-300" />
                    </div>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-bold text-[#1a1409] shadow-[0_10px_24px_rgba(251,191,36,0.13)] transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-100 focus-visible:ring-offset-2 focus-visible:ring-offset-[#0a0e12]">Confirmar activación</button>
                </form>
            </section>
        @elseif (! $enabled)
            <section class="rounded-xl border border-slate-800 bg-[#11171d] p-5 sm:p-6">
                <h2 class="text-lg font-bold text-white">Activa la protección</h2>
                <form method="POST" action="{{ route('two-factor.begin') }}" class="mt-5 max-w-2xl space-y-4">
                    @csrf
                    <div>
                        <label for="current_password" class="mb-1 block text-sm font-medium text-slate-300">Contraseña actual</label>
                        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required class="w-full rounded-md border-slate-700 bg-[#0c1116] text-sm text-white focus:border-amber-300 focus:ring-amber-300">
                        <x-input-error :messages="$errors->get('current_password')" class="mt-2 !text-rose-300" />
                    </div>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-bold text-[#1a1409] shadow-[0_10px_24px_rgba(251,191,36,0.13)] transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-100 focus-visible:ring-offset-2 focus-visible:ring-offset-[#0a0e12]">Iniciar configuración</button>
                </form>
            </section>
        @else
            <section class="rounded-xl border border-slate-800 bg-[#11171d] p-5 sm:p-6">
                <h2 class="text-lg font-bold text-white">Generar nuevos códigos de recuperación</h2>
                <p class="mt-2 text-sm leading-6 text-slate-400">Los códigos anteriores dejarán de funcionar.</p>
                <form method="POST" action="{{ route('two-factor.recovery') }}" class="mt-5 max-w-2xl space-y-4">
                    @csrf
                    <div>
                        <label for="recovery_current_password" class="mb-1 block text-sm font-medium text-slate-300">Contraseña actual</label>
                        <input id="recovery_current_password" name="current_password" type="password" autocomplete="current-password" required class="w-full rounded-md border-slate-700 bg-[#0c1116] text-sm text-white focus:border-amber-300 focus:ring-amber-300">
                    </div>
                    <div>
                        <label for="recovery_code" class="mb-1 block text-sm font-medium text-slate-300">Código de autenticación</label>
                        <input id="recovery_code" name="code" type="text" autocomplete="one-time-code" class="w-full rounded-md border-slate-700 bg-[#0c1116] text-sm text-white focus:border-amber-300 focus:ring-amber-300">
                    </div>
                    <div>
                        <label for="rotate_recovery_code" class="mb-1 block text-sm font-medium text-slate-300">O un código de recuperación</label>
                        <input id="rotate_recovery_code" name="recovery_code" autocomplete="off" class="w-full rounded-md border-slate-700 bg-[#0c1116] text-sm text-white focus:border-amber-300 focus:ring-amber-300">
                    </div>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-bold text-[#1a1409] shadow-[0_10px_24px_rgba(251,191,36,0.13)] transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-100 focus-visible:ring-offset-2 focus-visible:ring-offset-[#0a0e12]">Regenerar códigos</button>
                </form>
            </section>

            @if (! $required)
                <section class="rounded-xl border border-slate-800 bg-[#11171d] p-5 sm:p-6">
                    <h2 class="text-lg font-bold text-white">Desactivar autenticación en dos pasos</h2>
                    <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-5 max-w-2xl space-y-4">
                        @csrf
                        @method('DELETE')
                        <div>
                            <label for="disable_current_password" class="mb-1 block text-sm font-medium text-slate-300">Contraseña actual</label>
                            <input id="disable_current_password" name="current_password" type="password" autocomplete="current-password" required class="w-full rounded-md border-slate-700 bg-[#0c1116] text-sm text-white focus:border-amber-300 focus:ring-amber-300">
                        </div>
                        <div>
                            <label for="disable_code" class="mb-1 block text-sm font-medium text-slate-300">Código de autenticación o recuperación</label>
                            <input id="disable_code" name="code" type="text" autocomplete="one-time-code" class="w-full rounded-md border-slate-700 bg-[#0c1116] text-sm text-white focus:border-amber-300 focus:ring-amber-300">
                        </div>
                        <div>
                            <label for="disable_recovery_code" class="mb-1 block text-sm font-medium text-slate-300">O un código de recuperación</label>
                            <input id="disable_recovery_code" name="recovery_code" autocomplete="off" class="w-full rounded-md border-slate-700 bg-[#0c1116] text-sm text-white focus:border-amber-300 focus:ring-amber-300">
                        </div>
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-rose-500/40 px-4 py-2.5 text-sm font-bold text-rose-200 transition hover:border-rose-300 hover:bg-rose-500/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-300">Desactivar</button>
                    </form>
                </section>
            @endif
        @endif
    </div>
</x-account-security-layout>
