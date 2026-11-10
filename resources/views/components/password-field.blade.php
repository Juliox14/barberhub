@props(['id', 'tone' => 'dark'])

<div x-data="{ passwordVisible: false }" {{ $attributes->class(['relative']) }}>
    {{ $slot }}

    <button
        x-cloak
        type="button"
        aria-label="Mostrar contraseña"
        aria-controls="{{ $id }}"
        aria-pressed="false"
        x-on:click="passwordVisible = ! passwordVisible"
        x-bind:aria-label="passwordVisible ? 'Ocultar contraseña' : 'Mostrar contraseña'"
        x-bind:aria-pressed="passwordVisible"
        @class([
            'absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-md outline-none transition focus-visible:ring-2 focus-visible:ring-inset',
            'text-gray-500 hover:text-gray-700 focus-visible:ring-indigo-500' => $tone === 'light',
            'text-slate-400 hover:text-amber-200 focus-visible:ring-amber-200' => $tone !== 'light',
        ])
    >
        <svg x-show="! passwordVisible" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12S5.625 5.25 12 5.25 21.75 12 21.75 12 18.375 18.75 12 18.75 2.25 12Z"/><circle cx="12" cy="12" r="2.75"/></svg>
        <svg x-cloak x-show="passwordVisible" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18M10.58 10.58A2 2 0 0 0 13.4 13.4M9.88 5.36A10.7 10.7 0 0 1 12 5.25c6.375 0 9.75 6.75 9.75 6.75a18.6 18.6 0 0 1-3.03 3.74M6.23 6.23A18.75 18.75 0 0 0 2.25 12s3.375 6.75 9.75 6.75a10.8 10.8 0 0 0 3.43-.56"/></svg>
    </button>
</div>
