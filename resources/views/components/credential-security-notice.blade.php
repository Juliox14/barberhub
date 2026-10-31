@php
    $account = auth()->user()?->fresh();
    $factor = app(\App\Support\TwoFactorAuthentication::class);
    $needsEnrollment = $account && $factor->required($account) && ! $factor->enabled($account);
@endphp
@if ($needsEnrollment)
    <section role="status" class="mb-6 rounded-lg border border-amber-300/40 bg-amber-300/10 p-4 text-sm text-amber-200">
        <p class="font-semibold">Configura la autenticación en dos pasos</p>
        <p class="mt-2">Tu rol requiere una aplicación autenticadora. Configúrala antes del {{ $account->two_factor_enrollment_started_at?->copy()->addDays(14)->format('d/m/Y H:i') ?? 'próximo acceso' }}. Guarda los códigos de recuperación en un lugar seguro.</p>
        <a href="{{ route('two-factor.settings') }}" class="mt-2 inline-block font-semibold underline">Configurar 2FA</a>
    </section>
@endif
