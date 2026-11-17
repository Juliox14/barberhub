@php
    $googleCalendarConnected = $googleCalendarConnection?->connected_at !== null;
    $googleCalendarNeedsReconnect = $googleCalendarConnection?->sync_status === 'reconnect_needed';
@endphp

<x-modal name="google-calendar-connection" max-width="md" backdrop-class="bg-black/65" focusable>
    <div id="google-calendar-connection-dialog" class="space-y-5 bg-[#111820] p-5 text-left sm:p-6" role="dialog" aria-modal="true" aria-labelledby="google-calendar-connection-title">
        <header>
            <h2 id="google-calendar-connection-title" class="text-lg font-semibold text-white">Google Calendar</h2>
            <p class="mt-2 text-sm leading-6 text-slate-300">Conecta tu calendario personal para organizar tus citas de BarberHub. Se creará un calendario dedicado que puedes desconectar cuando quieras; tus eventos no se eliminarán.</p>
        </header>

        @if (session('calendar_error'))
            <p class="rounded-lg border border-amber-300/40 bg-amber-300/10 px-4 py-3 text-sm text-amber-100" role="alert">{{ session('calendar_error') }}</p>
        @endif

        @if ($googleCalendarNeedsReconnect)
            <p class="text-sm leading-6 text-amber-200" role="status">La autorización de Google necesita renovarse. Vuelve a conectar tu cuenta para continuar sincronizando.</p>
        @elseif ($googleCalendarConnected)
            <p class="text-sm font-semibold text-emerald-200" role="status">Google Calendar conectado.</p>
        @else
            <p class="text-sm text-slate-400" role="status">Todavía no hay una cuenta de Google conectada.</p>
        @endif

        @if ($googleCalendarNeedsReconnect || ! $googleCalendarConnected)
            <form method="POST" action="{{ route('tenant.google-calendar.connect', $barbershop) }}">
                @csrf
                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-semibold text-[#17130b] transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Continuar con Google</button>
            </form>
        @else
            <form method="POST" action="{{ route('tenant.google-calendar.disconnect', $barbershop) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Desconectar Google Calendar</button>
            </form>
        @endif

        <div class="flex justify-end">
            <button type="button" x-on:click="$dispatch('close')" class="min-h-11 rounded-lg px-4 py-2 text-sm font-semibold text-slate-300 transition hover:bg-slate-800 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Cerrar</button>
        </div>
    </div>
</x-modal>
