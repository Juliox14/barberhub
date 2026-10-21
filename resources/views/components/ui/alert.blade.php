@props(['type' => 'success', 'title' => null])

@php
    $isError = $type === 'error';
@endphp

<div
    x-data="{ visible: true }"
    x-init="setTimeout(() => visible = false, 5000)"
    x-show="visible"
    x-transition.opacity.duration.200ms
    {{ $attributes->merge([
        'role' => $isError ? 'alert' : 'status',
        'class' => 'bh-alert inline-flex max-w-full items-center gap-3 rounded-lg border px-4 py-3 text-sm leading-5 '.($isError
            ? 'border-[#3a2228] bg-[#1a0f12] text-[#ffd9de]'
            : 'border-[#1d2e25] bg-[#0f1a14] text-[#d5f2df]'),
    ]) }}
>
    {{-- Ícono --}}
    <span @class([
        'shrink-0',
        'text-[#f87171]' => $isError,
        'text-[#4ade80]' => ! $isError,
    ])>
        @if ($isError)
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M5 5l10 10M15 5L5 15" />
            </svg>
        @else
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 10.5l4 4 8-9" />
            </svg>
        @endif
    </span>

    {{-- Contenido --}}
    <div class="min-w-0 flex-1 break-words">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div @class(['mt-0.5' => $title])>{{ $slot }}</div>
    </div>

    {{-- Cerrar (discreto, solo ícono) --}}
    <button
        x-cloak
        type="button"
        x-on:click="visible = false"
        class="-mr-1 shrink-0 rounded p-1 opacity-60 transition hover:opacity-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current"
        aria-label="Cerrar aviso"
    >
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true">
            <path d="M5 5l10 10M15 5L5 15" />
        </svg>
    </button>
</div>