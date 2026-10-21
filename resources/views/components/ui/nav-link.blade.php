@props(['href', 'active' => false])

<a href="{{ $href }}" @class(['bh-nav-link', 'bh-nav-link--active' => $active]) @if ($active) aria-current="page" @endif {{ $attributes }}>
    {{ $slot }}
</a>
