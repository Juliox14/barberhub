<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-3xl font-bold tracking-[-0.035em] text-white">Configura tu contraseña</h1>
        <p class="mt-2 text-sm leading-6 text-slate-300">Al guardar tu contraseña, verificaremos este correo y podrás iniciar sesión en BarberHub.</p>
    </div>

    <form method="POST" action="{{ route('barber-invitations.complete') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">

        <div>
            <label for="email_display" class="block text-sm font-semibold text-slate-100">Correo electrónico</label>
            <input id="email_display" class="mt-2 block w-full rounded-xl border border-slate-600 bg-[#0b1015] px-4 py-3 text-sm text-slate-300 shadow-inner shadow-black/20 disabled:cursor-not-allowed" type="email" value="{{ $email }}" disabled autocomplete="username" />
        </div>

        <div>
            <label for="password" class="block text-sm font-semibold text-slate-100">Nueva contraseña</label>
            <input id="password" class="mt-2 block w-full rounded-xl border border-slate-600 bg-[#0b1015] px-4 py-3 text-sm text-white placeholder:text-slate-500 shadow-inner shadow-black/20 outline-none transition focus:border-amber-300 focus:ring-2 focus:ring-amber-300/25" type="password" name="password" required autofocus autocomplete="new-password" />
            <x-password-requirements />
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-semibold text-slate-100">Confirma la contraseña</label>
            <input id="password_confirmation" class="mt-2 block w-full rounded-xl border border-slate-600 bg-[#0b1015] px-4 py-3 text-sm text-white placeholder:text-slate-500 shadow-inner shadow-black/20 outline-none transition focus:border-amber-300 focus:ring-2 focus:ring-amber-300/25" type="password" name="password_confirmation" required autocomplete="new-password" />
        </div>


        <div class="flex items-center justify-end">
            <button type="submit" class="flex w-full items-center justify-center rounded-xl bg-amber-300 px-4 py-3.5 text-sm font-bold text-[#1a1409] shadow-[0_10px_24px_rgba(251,191,36,0.16)] outline-none transition hover:bg-amber-200 focus-visible:ring-2 focus-visible:ring-amber-100 focus-visible:ring-offset-2 focus-visible:ring-offset-[#10151a] active:bg-amber-400">Configurar contraseña</button>
        </div>
    </form>
</x-guest-layout>
