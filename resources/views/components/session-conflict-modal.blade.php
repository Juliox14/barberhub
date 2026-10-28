<div
    x-data="{
        cancel() { $refs.cancelForm.requestSubmit() },
        trap(event) {
            const controls = [...$el.querySelectorAll('button:not([disabled])')];
            const current = controls.indexOf(document.activeElement);
            if (event.shiftKey && current === 0) {
                event.preventDefault();
                controls[controls.length - 1].focus();
            } else if (! event.shiftKey && current === controls.length - 1) {
                event.preventDefault();
                controls[0].focus();
            }
        }
    }"
    x-on:keydown.escape.window="cancel()"
    x-on:keydown.tab="trap($event)"
    x-on:click.self="cancel()"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 px-4 py-6"
    role="presentation"
>
    <section
        x-init="$nextTick(() => $refs.cancel.focus())"
        class="w-full max-w-md rounded-xl border border-slate-700 bg-[#10151a] p-6 text-white shadow-[0_18px_48px_rgba(0,0,0,0.45)]"
        role="dialog"
        aria-modal="true"
        aria-labelledby="session-conflict-title"
        aria-describedby="session-conflict-description"
        tabindex="-1"
    >
        <h2 id="session-conflict-title" class="text-xl font-bold tracking-[-0.02em]">Ya hay una sesión abierta</h2>
        <p id="session-conflict-description" class="mt-3 text-sm leading-6 text-slate-300">Tu cuenta está abierta en otro dispositivo. ¿Quieres cambiarla a este dispositivo? La sesión anterior se cerrará.</p>
        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <form x-ref="cancelForm" method="POST" action="{{ route('login.session-conflict.cancel') }}">
                @csrf
                <button x-ref="cancel" type="submit" class="w-full rounded-lg border border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-200 outline-none transition hover:bg-slate-800 focus-visible:ring-2 focus-visible:ring-amber-200 sm:w-auto">Salir</button>
            </form>
            <form method="POST" action="{{ route('login.session-conflict.confirm') }}">
                @csrf
                <button type="submit" class="w-full rounded-lg bg-amber-300 px-4 py-2.5 text-sm font-bold text-[#1a1409] outline-none transition hover:bg-amber-200 focus-visible:ring-2 focus-visible:ring-amber-100 sm:w-auto">Cambiar a este dispositivo</button>
            </form>
        </div>
    </section>
</div>
