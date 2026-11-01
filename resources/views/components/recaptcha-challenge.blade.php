@props([
    'required' => false,
    'available' => false,
    'siteKey' => null,
])

@if ($required)
    <div>
        <button
            type="button"
            data-recaptcha-open
            aria-expanded="false"
            style="display: none;"
            class="fixed bottom-5 right-5 z-40 rounded-xl border border-amber-200/50 bg-[#10151a] px-4 py-3 text-sm font-semibold text-amber-100 shadow-[0_12px_32px_rgba(0,0,0,0.45)] outline-none transition hover:bg-[#1b232b] focus-visible:ring-2 focus-visible:ring-amber-200 focus-visible:ring-offset-2 focus-visible:ring-offset-[#080a0d]"
            hidden
        >
            Completar verificación
        </button>

        <div
            data-recaptcha-modal
            style="display: none;"
            class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-black/75 p-4"
            hidden
        >
            <section
                data-recaptcha-dialog
                role="dialog"
                aria-modal="false"
                aria-labelledby="recaptcha-modal-title"
                aria-describedby="recaptcha-modal-description"
                tabindex="-1"
                class="w-full max-w-md rounded-2xl border border-slate-600 bg-[#10151a] p-5 text-slate-100 shadow-[0_24px_70px_rgba(0,0,0,0.55)] outline-none sm:p-6"
            >
                <div class="flex items-start justify-between gap-5">
                    <div>
                        <h2 id="recaptcha-modal-title" class="text-lg font-bold text-white">Verificación de seguridad</h2>
                        <p id="recaptcha-modal-description" class="mt-2 text-sm leading-6 text-slate-200">
                            Para proteger tu cuenta, confirma que no eres un robot antes de continuar.
                        </p>
                    </div>
                    <button
                        type="button"
                        data-recaptcha-close
                        aria-label="Cerrar verificación"
                        class="shrink-0 rounded-lg px-3 py-2 text-sm font-semibold text-slate-200 outline-none transition hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-amber-200"
                    >
                        Cerrar
                    </button>
                </div>

                @error('g-recaptcha-response')
                    <p role="alert" class="mt-4 text-sm text-rose-300">{{ $message }}</p>
                @enderror

                <p data-recaptcha-status role="status" aria-live="polite" class="mt-4 min-h-6 text-sm text-amber-100"></p>

                @if ($available)
                    <div class="mt-4 flex justify-center">
                        <div
                            class="g-recaptcha"
                            data-sitekey="{{ $siteKey }}"
                            data-theme="dark"
                            data-size="compact"
                            data-callback="barberHubRecaptchaSuccess"
                            data-expired-callback="barberHubRecaptchaExpired"
                            data-error-callback="barberHubRecaptchaError"
                        ></div>
                    </div>
                    <script async defer src="https://www.google.com/recaptcha/api.js"></script>
                @else
                    <p role="alert" class="mt-4 text-sm text-amber-100">
                        La verificación de seguridad no está disponible en este momento. Inténtalo más tarde.
                    </p>
                @endif
            </section>
        </div>

        @if ($available)
            <noscript>
                <p role="alert" class="fixed bottom-5 left-5 z-40 max-w-sm rounded-xl border border-amber-200/40 bg-[#10151a] p-4 text-sm leading-6 text-amber-100 shadow-[0_12px_32px_rgba(0,0,0,0.45)]">
                    Activa JavaScript para completar la verificación y volver a intentarlo.
                </p>
            </noscript>
        @endif
    </div>
@endif
