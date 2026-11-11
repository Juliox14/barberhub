<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" active-page="google-calendar">
    <div class="mx-auto max-w-3xl space-y-6">
        <header>
            <h1 class="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">Google Calendar</h1>
            <p class="mt-1 text-sm leading-6 text-slate-400">Conecta tu calendario personal de trabajo para organizar tus citas de BarberHub.</p>
        </header>

        @if (session('calendar_error'))
            <p class="rounded-lg border border-amber-300/40 bg-amber-300/10 px-4 py-3 text-sm text-amber-100" role="alert">{{ session('calendar_error') }}</p>
        @endif

        @if ($syncStatus === 'reconnect_needed')
            <section class="space-y-4 rounded-xl border border-amber-300/40 bg-[#111820] p-5 sm:p-6" role="status">
                <p class="font-semibold text-amber-200">Vuelve a conectar Google Calendar</p>
                <p class="text-sm leading-6 text-slate-300">{{ $syncError ?: 'La autorización de Google necesita renovarse.' }}</p>
                <form method="POST" action="{{ route('tenant.google-calendar.connect', $barbershop) }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-semibold text-[#17130b]">Conectar de nuevo</button>
                </form>
            </section>
        @elseif ($connected)
            <section class="space-y-4 rounded-xl border border-slate-800 bg-[#111820] p-5 sm:p-6">
                <p class="font-semibold text-emerald-200">Google Calendar conectado</p>
                <p class="text-sm leading-6 text-slate-300">Las futuras citas se administrarán en el calendario dedicado de BarberHub.</p>
                <form method="POST" action="{{ route('tenant.google-calendar.disconnect', $barbershop) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex min-h-11 items-center rounded-lg border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-200 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Desconectar Google Calendar</button>
                </form>
            </section>
        @else
            <section class="space-y-4 rounded-xl border border-slate-800 bg-[#111820] p-5 sm:p-6">
                <p class="text-sm leading-6 text-slate-300">Se creará un calendario dedicado de BarberHub en tu cuenta de Google. Puedes desconectarlo cuando quieras; el calendario y sus eventos no se eliminarán.</p>
                <form method="POST" action="{{ route('tenant.google-calendar.connect', $barbershop) }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-300 px-5 py-2.5 text-sm font-semibold text-[#17130b] hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Conectar Google Calendar</button>
                </form>
            </section>
        @endif

        <p class="text-xs text-slate-500">Iconos de <a href="https://icons8.com/icon/UJGaYqXaHWdp/google-calendar" class="underline underline-offset-2 hover:text-slate-300">Icons8</a>.</p>
    </div>
</x-tenant-layout>
