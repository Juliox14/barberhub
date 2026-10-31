<x-guest-layout>
    <h1 class="text-2xl font-bold text-white">Verifica tu identidad</h1>
    <p class="mt-3 text-sm text-slate-300">Ingresa el código de tu aplicación autenticadora o uno de tus códigos de recuperación. No compartas estos códigos.</p>
    <form method="POST" action="{{ route('two-factor.verify') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="code" class="block text-sm font-semibold text-slate-100">Código de autenticación</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" class="mt-2 block w-full rounded-xl border border-slate-600 bg-[#0b1015] px-4 py-3 text-sm text-white placeholder:text-slate-500 shadow-inner shadow-black/20 outline-none transition focus:border-amber-300 focus:ring-2 focus:ring-amber-300/25" autofocus />
        </div>
        <p class="text-sm text-slate-300">O, si no tienes acceso a tu aplicación:</p>
        <div>
            <label for="recovery_code" class="block text-sm font-semibold text-slate-100">Código de recuperación de un solo uso</label>
            <input id="recovery_code" name="recovery_code" autocomplete="off" class="mt-2 block w-full rounded-xl border border-slate-600 bg-[#0b1015] px-4 py-3 text-sm text-white placeholder:text-slate-500 shadow-inner shadow-black/20 outline-none transition focus:border-amber-300 focus:ring-2 focus:ring-amber-300/25" />
        </div>
        <button type="submit" class="flex w-full items-center justify-center rounded-xl bg-amber-300 px-4 py-3.5 text-sm font-bold text-[#1a1409] shadow-[0_10px_24px_rgba(251,191,36,0.16)] outline-none transition hover:bg-amber-200 focus-visible:ring-2 focus-visible:ring-amber-100 focus-visible:ring-offset-2 focus-visible:ring-offset-[#10151a] active:bg-amber-400">Verificar y continuar</button>
    </form>
    @auth
        <form method="POST" action="{{ route('logout') }}" class="mt-4">
            @csrf
            <button type="submit" class="flex w-full items-center justify-center rounded-xl border border-slate-600 bg-[#0b1015] px-4 py-3 text-sm font-semibold text-slate-300 outline-none transition hover:border-slate-400 hover:text-white focus-visible:ring-2 focus-visible:ring-amber-200 focus-visible:ring-offset-2 focus-visible:ring-offset-[#10151a]">Cerrar sesión</button>
        </form>
    @else
        <form method="POST" action="{{ route('two-factor.cancel') }}" class="mt-4">
            @csrf
            <button type="submit" class="flex w-full items-center justify-center rounded-xl border border-slate-600 bg-[#0b1015] px-4 py-3 text-sm font-semibold text-slate-300 outline-none transition hover:border-slate-400 hover:text-white focus-visible:ring-2 focus-visible:ring-amber-200 focus-visible:ring-offset-2 focus-visible:ring-offset-[#10151a]">Volver al inicio de sesión</button>
        </form>
    @endauth
</x-guest-layout>
