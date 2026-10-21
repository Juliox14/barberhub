@props(['type' => 'success', 'title' => null])

<div
    x-data="{ visible: true }"
    x-init="setTimeout(() => visible = false, 5000)"
    x-show="visible"
    {{ $attributes->merge([
        'role' => $type === 'error' ? 'alert' : 'status',
        'class' => 'bh-alert flex items-start gap-4 rounded-xl px-5 py-4 text-sm leading-6 '.($type === 'error'
            ? 'bg-[#2b1c21] text-[#ffd9de]'
            : 'bg-[#16251f] text-[#d5f2df]'),
    ]) }}
>
    <div class="min-w-0 flex-1 break-words">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div @class(['mt-1' => $title])>{{ $slot }}</div>
    </div>
    <button
        x-cloak
        type="button"
        x-on:click="visible = false"
        class="-my-1 shrink-0 rounded px-2 py-1 font-semibold underline-offset-4 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current"
        aria-label="Cerrar aviso"
    >Cerrar</button>
</div>
