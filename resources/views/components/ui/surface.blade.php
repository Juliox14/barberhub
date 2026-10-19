@props(['as' => 'section', 'tone' => 'default'])

<{{ $as }} {{ $attributes->class(['bh-surface', 'bh-surface--muted' => $tone === 'muted']) }}>
    {{ $slot }}
</{{ $as }}>
