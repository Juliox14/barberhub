<section class="mb-5 rounded-xl border border-slate-800 bg-[#111820] p-4 sm:flex sm:items-center sm:justify-between sm:gap-5" aria-label="Instalación y conexión de BarberHub" data-pwa-controls>
    <div>
        <p class="font-semibold text-slate-100">BarberHub en tu celular</p>
        <p class="mt-1 text-sm leading-6 text-slate-400">Instala el acceso para abrir más rápido tu espacio de barbería.</p>
        <p class="mt-2 hidden text-sm leading-6 text-amber-200" data-ios-install>
            En Safari: toca Compartir y selecciona «Agregar a pantalla de inicio».
        </p>
    </div>
    <div class="mt-4 flex flex-col items-start gap-2 sm:mt-0 sm:items-end" data-push-controls data-subscribe-url="{{ route('tenant.push-subscriptions.store', request()->route('barbershop')) }}" data-vapid-public-key="{{ config('webpush.public_key') }}">
        <button type="button" class="hidden min-h-11 w-full rounded-lg border border-amber-300/40 bg-amber-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 disabled:cursor-wait disabled:opacity-60 sm:w-auto" data-push-enable>
            Activar notificaciones
        </button>
        <p class="text-sm leading-6 text-slate-400" role="status" aria-live="polite" data-push-status>
            Comprueba si este dispositivo admite notificaciones push.
        </p>
        <p class="hidden text-sm leading-6 text-amber-200" data-ios-push-install>
            En iPhone o iPad, instala BarberHub en la pantalla de inicio antes de activar las notificaciones.
        </p>
    </div>
    <button type="button" class="mt-4 hidden min-h-11 w-full rounded-lg border border-amber-300/40 bg-amber-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 sm:mt-0 sm:w-auto" data-pwa-install>
        Agregar BarberHub a la pantalla de inicio
    </button>
    <p class="mt-4 hidden text-sm text-slate-400 sm:mt-0" data-pwa-unavailable>
        La instalación no está disponible en este navegador. Puedes seguir usando BarberHub.
    </p>
</section>
<div class="mb-5 hidden rounded-lg border border-amber-300/40 bg-amber-300/10 px-4 py-3 text-sm font-medium text-amber-100" role="status" aria-live="polite" data-pwa-offline>
    Sin conexión. Agenda y citas no disponibles; ninguna operación se guardará. Espera a recuperar internet.
</div>
