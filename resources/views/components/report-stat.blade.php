@props(['label', 'value', 'description'])

<article class="rounded-xl border border-slate-800 bg-[#111820] p-5">
    <h2 class="text-sm font-medium text-slate-300">{{ $label }}</h2>
    <p class="mt-2 text-3xl font-bold tabular-nums tracking-tight text-white">{{ number_format((int) $value) }}</p>
    <p class="mt-3 text-xs leading-5 text-slate-400">{{ $description }}</p>
</article>
