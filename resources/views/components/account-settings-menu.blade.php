<details class="relative" data-account-settings-menu>
    <summary class="flex min-h-11 min-w-11 cursor-pointer list-none items-center justify-center rounded-md p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 [&::-webkit-details-marker]:hidden" aria-label="Abrir menú de configuración de cuenta" title="Configuración de cuenta">
        <img src="{{ asset('images/icons/account-settings.png') }}" alt="" class="h-5 w-5">
    </summary>
    <div class="absolute right-0 top-full z-50 mt-2 w-56 max-w-[calc(100vw-2rem)] rounded-lg border border-slate-700 bg-[#111820] p-2 shadow-xl">
        <a href="{{ route('two-factor.settings') }}" class="block rounded-md px-3 py-2.5 text-sm font-medium text-slate-200 hover:bg-slate-800 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Seguridad de mi cuenta</a>
    </div>
</details>
