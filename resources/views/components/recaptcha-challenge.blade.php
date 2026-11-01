@props([
    'required' => false,
    'available' => false,
    'siteKey' => null,
])

@if ($required)
    <section class="rounded-xl border border-slate-700 bg-[#0b1015] p-4" aria-labelledby="recaptcha-instructions">
        <p id="recaptcha-instructions" class="mb-3 text-sm leading-6 text-slate-200">
            Para proteger tu cuenta, confirma que no eres un robot antes de continuar.
        </p>

        @error('g-recaptcha-response')
            <p role="alert" class="mb-3 text-sm text-rose-300">{{ $message }}</p>
        @enderror

        @if ($available)
            <div class="g-recaptcha" data-sitekey="{{ $siteKey }}" data-theme="dark" data-size="compact"></div>
            <script async defer src="https://www.google.com/recaptcha/api.js"></script>
            <noscript>
                <p class="mt-3 text-sm text-amber-200">Activa JavaScript para completar la verificación y volver a intentarlo.</p>
            </noscript>
        @else
            <p role="alert" class="text-sm text-amber-200">
                La verificación de seguridad no está disponible en este momento. Inténtalo más tarde.
            </p>
        @endif
    </section>
@endif
