<x-account-security-layout title="Renueva tu contraseña">
    <div class="mx-auto max-w-7xl space-y-6">
        <header>
            <h1 class="text-3xl font-bold tracking-[-0.035em] text-white sm:text-4xl">Renueva tu contraseña</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">Tu contraseña ha vencido. Por seguridad, elige una contraseña nueva para continuar usando BarberHub.</p>
        </header>

        <section class="rounded-xl border border-slate-800 bg-[#11171d] p-5 sm:p-6" aria-labelledby="password-renewal-heading">
            <h2 id="password-renewal-heading" class="text-lg font-bold text-white">Nueva contraseña</h2>
            <form method="post" action="{{ route('password.update') }}" class="mt-5 max-w-2xl space-y-5">
                @csrf
                @method('put')

                <div>
                    <label for="current_password" class="mb-1 block text-sm font-medium text-slate-300">Contraseña actual</label>
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="w-full rounded-md border-slate-700 bg-[#0c1116] text-sm text-white focus:border-amber-300 focus:ring-amber-300">
                    <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2 !text-rose-300" />
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-slate-300">Nueva contraseña</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password" class="w-full rounded-md border-slate-700 bg-[#0c1116] text-sm text-white focus:border-amber-300 focus:ring-amber-300">
                    <x-password-requirements />
                    <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2 !text-rose-300" />
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1 block text-sm font-medium text-slate-300">Confirma la contraseña</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="w-full rounded-md border-slate-700 bg-[#0c1116] text-sm text-white focus:border-amber-300 focus:ring-amber-300">
                </div>

                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-bold text-[#1a1409] shadow-[0_10px_24px_rgba(251,191,36,0.13)] transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-100 focus-visible:ring-offset-2 focus-visible:ring-offset-[#0a0e12]">Renovar contraseña</button>
            </form>
        </section>
    </div>
</x-account-security-layout>
