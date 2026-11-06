@props([
    'column',
    'label',
    'sort',
    'direction',
    'pageParameter' => null,
])

@php
    $query = request()->query();
    if ($pageParameter !== null) {
        unset($query[$pageParameter]);
    }
    $nextDirection = $sort === $column
        ? ($direction === 'asc' ? 'desc' : 'asc')
        : 'asc';
    $query['sort'] = $column;
    $query['direction'] = $nextDirection;
    $routeParameters = [...$query, ...request()->route()->parameters()];
@endphp

<span {{ $attributes->merge(['class' => '']) }} aria-sort="{{ $sort === $column ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}" data-sort="{{ $column }}">
    <a href="{{ route(request()->route()->getName(), $routeParameters, false) }}" class="inline-flex items-center gap-1.5 rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" aria-label="Ordenar {{ $label }} {{ $nextDirection === 'asc' ? 'ascendente' : 'descendente' }}">
        {{ $label }}
        <span aria-hidden="true" class="text-amber-200">{{ $sort === $column ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</span>
    </a>
</span>
