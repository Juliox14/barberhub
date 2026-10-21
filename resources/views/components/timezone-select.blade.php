@props([
    'id' => 'timezone',
    'options' => [],
    'selected' => null,
    'legacyHint' => null,
])

@php
    $isLegacyValue = $selected !== null && ! collect($options)->contains(fn (array $countryOptions): bool => array_key_exists($selected, $countryOptions));
@endphp

<div x-data="{
    query: '',
    filterOptions() {
        const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
        const term = normalize(this.query.trim());
        const select = this.$refs.select;
        let matchingCount = 0;

        for (const group of select.querySelectorAll('optgroup')) {
            let groupCount = 0;

            for (const option of group.querySelectorAll('option')) {
                const matches = !term || normalize(option.textContent).includes(term);
                option.hidden = !matches && !option.selected;
                groupCount += matches || option.selected ? 1 : 0;
                matchingCount += matches ? 1 : 0;
            }

            group.hidden = groupCount === 0;
        }

        this.$refs.empty.hidden = matchingCount > 0;
    }
}" x-init="filterOptions()">
    <label for="{{ $id }}" class="block text-sm font-semibold text-slate-100">Zona horaria</label>
    <input id="{{ $id }}-search" type="search" x-cloak x-model="query" x-on:input="filterOptions()" autocomplete="off" aria-label="Buscar zona horaria" placeholder="Buscar país o ciudad…" class="mt-2 block w-full rounded-lg border border-slate-700 bg-[#0b1015] px-4 py-3 text-sm text-white placeholder:text-slate-500 outline-none transition focus:border-amber-300 focus:ring-2 focus:ring-amber-300/20" />
    <p x-ref="empty" hidden class="mt-2 text-xs text-slate-400" role="status">No hay zonas que coincidan con la búsqueda. La zona seleccionada se conserva.</p>
    <select id="{{ $id }}" name="timezone" x-ref="select" required class="mt-2 block w-full rounded-lg border border-slate-700 bg-[#0b1015] px-4 py-3 text-sm text-white outline-none transition focus:border-amber-300 focus:ring-2 focus:ring-amber-300/20">
        @if ($isLegacyValue)
            <option value="{{ $selected }}" selected disabled>{{ $selected }} (fuera de la lista regional)</option>
        @endif
        @foreach ($options as $country => $countryOptions)
            <optgroup label="{{ $country }}">
                @foreach ($countryOptions as $identifier => $label)
                    <option value="{{ $identifier }}" @selected($identifier === $selected)>{{ $label }}</option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
    <p class="mt-2 text-xs leading-5 text-slate-500">Busca y selecciona una zona horaria de Latinoamérica. {{ $legacyHint ?? '' }} @if ($isLegacyValue) Elige una zona de la lista para actualizar este registro. @endif</p>
</div>
