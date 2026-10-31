@props(['barbershop' => null])

<div data-pwa-controls data-account-id="{{ $accountId }}" data-push-subscribed="{{ $pushSubscribed ? 'true' : 'false' }}">
    <div class="fixed inset-x-3 bottom-[max(1rem,env(safe-area-inset-bottom))] z-50 mx-auto flex max-w-lg flex-col gap-3" aria-label="Avisos de BarberHub">
        <section class="hidden rounded-xl border border-slate-800 bg-[#111820] p-4 shadow-lg" role="alert" aria-label="Instalación de BarberHub" data-pwa-install-alert>
            <div class="flex items-start gap-3">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-slate-100">BarberHub en tu celular</p>
                    <p class="mt-1 text-sm leading-6 text-slate-300">Instala el acceso para abrir más rápido tu espacio de barbería.</p>
                    <p class="mt-2 hidden text-sm leading-6 text-amber-200" data-ios-install>En Safari: toca Compartir y selecciona «Agregar a pantalla de inicio».</p>
                    <button type="button" class="mt-3 hidden min-h-11 w-full rounded-lg border border-amber-300/40 bg-amber-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950" data-pwa-install>Agregar BarberHub a la pantalla de inicio</button>
                    <p class="mt-3 hidden text-sm text-slate-300" data-pwa-unavailable>La instalación no está disponible en este navegador.</p>
                </div>
                <button type="button" class="flex min-h-11 min-w-11 items-center justify-center rounded-lg text-slate-300 hover:bg-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" aria-label="Cerrar aviso de instalación" data-pwa-dismiss="install"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg></button>
            </div>
        </section>
        <section class="hidden rounded-xl border border-slate-800 bg-[#111820] p-4 shadow-lg" role="alert" aria-label="Notificaciones de BarberHub" data-push-alert>
            <div class="flex items-start gap-3">
                <div class="min-w-0 flex-1" data-push-controls data-subscribe-url="{{ route('tenant.push-subscriptions.store', $barbershop ?? request()->route('barbershop')) }}" data-vapid-public-key="{{ config('webpush.public_key') }}">
                    <p class="font-semibold text-slate-100">Avisos de tu agenda</p>
                    <p class="mt-1 text-sm leading-6 text-slate-300" role="status" aria-live="polite" data-push-status>Comprueba si este dispositivo admite notificaciones push.</p>
                    <p class="mt-2 hidden text-sm leading-6 text-amber-200" data-ios-push-install>En iPhone o iPad, instala BarberHub en la pantalla de inicio antes de activar las notificaciones.</p>
                    <button type="button" class="mt-3 hidden min-h-11 w-full rounded-lg border border-amber-300/40 bg-amber-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 disabled:cursor-wait disabled:opacity-60" data-push-enable>Activar notificaciones</button>
                </div>
                <button type="button" class="flex min-h-11 min-w-11 items-center justify-center rounded-lg text-slate-300 hover:bg-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" aria-label="Cerrar aviso de notificaciones" data-pwa-dismiss="push"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg></button>
            </div>
        </section>
    </div>
    <div class="mb-5 hidden rounded-lg border border-amber-300/40 bg-amber-300/10 px-4 py-3 text-sm font-medium text-amber-100" role="status" aria-live="polite" data-pwa-offline>
        Sin conexión. Agenda y citas no disponibles; ninguna operación se guardará. Espera a recuperar internet.
    </div>
</div>
