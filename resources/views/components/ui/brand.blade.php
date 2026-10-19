@props(['href'])

<a href="{{ $href }}" {{ $attributes->class(['bh-brand']) }}>
    <img src="{{ asset('images/logo.png') }}" alt="" class="h-10 w-10 shrink-0 object-contain" />
    <span>BarberHub</span>
</a>
