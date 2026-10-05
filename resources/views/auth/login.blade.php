<x-guest-layout>
    <nav class="mb-8 grid grid-cols-2 rounded-xl border border-slate-700 bg-[#0a0e12] p-1" aria-label="Acceso a cuenta">
        <a href="{{ route('login') }}" aria-current="page" class="rounded-lg border border-amber-300/60 bg-amber-300/10 px-3 py-2.5 text-center text-sm font-bold text-amber-200 shadow-sm outline-none transition hover:bg-amber-300/15 focus-visible:ring-2 focus-visible:ring-amber-200 focus-visible:ring-offset-2 focus-visible:ring-offset-[#0a0e12]">
            Iniciar sesión
        </a>
        <a href="{{ route('register') }}" class="rounded-lg px-3 py-2.5 text-center text-sm font-semibold text-slate-300 outline-none transition hover:text-white focus-visible:ring-2 focus-visible:ring-amber-200 focus-visible:ring-offset-2 focus-visible:ring-offset-[#0a0e12]">
            Crear cuenta
        </a>
    </nav>

    <header class="mb-8">
        <h1 class="text-3xl font-bold tracking-[-0.035em] text-white">Bienvenido de nuevo</h1>
        <p class="mt-2 text-sm leading-6 text-slate-300">Ingresa a tu cuenta para continuar administrando tu barbería.</p>
    </header>

    <x-auth-session-status class="mb-6 rounded-lg border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-sm font-semibold text-slate-100">Correo electrónico</label>
            <div class="relative mt-2">
                <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9 6 9-6"/></svg>
                <input id="email" class="block w-full rounded-xl border border-slate-600 bg-[#0b1015] py-3 pl-11 pr-4 text-sm text-white placeholder:text-slate-500 shadow-inner shadow-black/20 outline-none transition focus:border-amber-300 focus:ring-2 focus:ring-amber-300/25" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="tu@correo.com" />
            </div>
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-amber-200" />
        </div>

        <div x-data="{ visible: false }">
            <div class="flex items-center justify-between gap-4">
                <label for="password" class="block text-sm font-semibold text-slate-100">Contraseña</label>
                @if (Route::has('password.request'))
                    <a class="text-sm font-semibold text-amber-200 underline decoration-amber-200/50 underline-offset-4 outline-none transition hover:text-amber-100 focus-visible:ring-2 focus-visible:ring-amber-200 focus-visible:ring-offset-2 focus-visible:ring-offset-[#10151a]" href="{{ route('password.request') }}">
                        ¿Olvidaste tu contraseña?
                    </a>
                @endif
            </div>
            <div class="relative mt-2">
                <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="10" width="16" height="10" rx="2"/><path stroke-linecap="round" d="M8 10V7a4 4 0 0 1 8 0v3"/><path stroke-linecap="round" d="M12 14v2"/></svg>
                <input id="password" class="block w-full rounded-xl border border-slate-600 bg-[#0b1015] py-3 pl-11 pr-12 text-sm text-white placeholder:text-slate-500 shadow-inner shadow-black/20 outline-none transition focus:border-amber-300 focus:ring-2 focus:ring-amber-300/25" x-bind:type="visible ? 'text' : 'password'" name="password" required autocomplete="current-password" placeholder="Tu contraseña" />
                <button type="button" x-on:click="visible = ! visible" x-bind:aria-label="visible ? 'Ocultar contraseña' : 'Mostrar contraseña'" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-xl text-slate-400 outline-none transition hover:text-amber-200 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-200">
                    <svg x-show="! visible" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12S5.625 5.25 12 5.25 21.75 12 21.75 12 18.375 18.75 12 18.75 2.25 12 2.25 12Z"/><circle cx="12" cy="12" r="2.75"/></svg>
                    <svg x-cloak x-show="visible" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18M10.58 10.58A2 2 0 0 0 13.4 13.4M9.88 5.36A10.7 10.7 0 0 1 12 5.25c6.375 0 9.75 6.75 9.75 6.75a18.6 18.6 0 0 1-3.03 3.74M6.23 6.23A18.75 18.75 0 0 0 2.25 12s3.375 6.75 9.75 6.75a10.8 10.8 0 0 0 3.43-.56"/></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-amber-200" />
        </div>

        <label for="remember" class="flex w-fit cursor-pointer items-center gap-3 text-sm text-slate-300">
            <input id="remember" type="checkbox" class="h-4 w-4 rounded border-slate-500 bg-[#0b1015] text-amber-300 focus:ring-amber-300/40" name="remember">
            Recordarme
        </label>

        <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-amber-300 px-4 py-3.5 text-sm font-bold text-[#1a1409] shadow-[0_10px_24px_rgba(251,191,36,0.16)] outline-none transition hover:bg-amber-200 focus-visible:ring-2 focus-visible:ring-amber-100 focus-visible:ring-offset-2 focus-visible:ring-offset-[#10151a] active:bg-amber-400">
            Entrar
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 10h12m-5-5 5 5-5 5"/></svg>
        </button>
    </form>

    <p class="mt-8 text-center text-sm text-slate-300">
        ¿No tienes una cuenta?
        <a href="{{ route('register') }}" class="ml-1 font-semibold text-amber-200 underline decoration-amber-200/50 underline-offset-4 outline-none transition hover:text-amber-100 focus-visible:ring-2 focus-visible:ring-amber-200 focus-visible:ring-offset-2 focus-visible:ring-offset-[#10151a]">Crear cuenta</a>
    </p>
</x-guest-layout>
