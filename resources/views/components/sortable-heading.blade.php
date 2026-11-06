@props([
    'column',
    'label',
    'sort',
    'direction',
    'sortParameter' => 'sort',
    'directionParameter' => 'direction',
    'pageParameter' => 'page',
])

@php
    $query = request()->query();
    unset($query[$pageParameter]);
    $nextDirection = $sort === $column
        ? ($direction === 'asc' ? 'desc' : 'asc')
        : 'asc';
    $query[$sortParameter] = $column;
    $query[$directionParameter] = $nextDirection;
@endphp

<th scope="col" {{ $attributes->merge(['class' => 'px-5 py-4 font-semibold']) }} aria-sort="{{ $sort === $column ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}" data-sort="{{ $column }}">
    <a href="{{ route(request()->route()->getName(), $query, false) }}" class="inline-flex items-center gap-1.5 rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" aria-label="Ordenar {{ $label }} {{ $nextDirection === 'asc' ? 'ascendente' : 'descendente' }}">
        {{ $label }}
        <span aria-hidden="true" class="text-amber-200">{{ $sort === $column ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</span>
    </a>
</th>
