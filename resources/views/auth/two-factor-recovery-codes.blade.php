<x-account-security-layout :title="$title">
    <div class="mx-auto max-w-7xl space-y-6">
        <header>
            <h1 class="text-3xl font-bold tracking-[-0.035em] text-white sm:text-4xl">{{ $title }}</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">Guárdalos en un lugar seguro. Cada código solo puede usarse una vez y no volverán a mostrarse.</p>
        </header>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-[#11171d]" aria-label="Códigos de recuperación">
            <ul class="grid min-w-0 grid-cols-1 divide-y divide-slate-800 sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                @foreach ($recoveryCodes as $recoveryCode)
                    <li class="min-w-0 break-all px-5 py-4 font-mono text-sm text-amber-100 sm:px-6">{{ $recoveryCode }}</li>
                @endforeach
            </ul>
        </section>

        <a class="inline-flex min-h-11 items-center rounded-md text-sm font-semibold text-amber-200 underline decoration-amber-200/40 underline-offset-4 outline-none hover:text-amber-100 focus-visible:ring-2 focus-visible:ring-amber-300" href="{{ route('two-factor.settings') }}">Volver a la configuración</a>
    </div>
</x-account-security-layout>
