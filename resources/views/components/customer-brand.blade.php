@props(['barbershop', 'branding' => null, 'preview' => false])

@php
    $tokens = $branding ?? \App\Support\TenantBranding::resolve($barbershop);
@endphp

<div
    {{ $attributes->class(['bh-customer-brand']) }}
    style="--bh-brand-primary: {{ $tokens['primary'] }}; --bh-brand-accent: {{ $tokens['accent'] }}; --bh-brand-primary-ink: {{ $tokens['primary_foreground'] }}; --bh-brand-accent-ink: {{ $tokens['accent_foreground'] }}; --bh-brand-surface: {{ $tokens['surface'] }};"
    @if ($preview) :style="{ '--bh-brand-primary': values.primary, '--bh-brand-accent': values.accent, '--bh-brand-primary-ink': values.primaryForeground, '--bh-brand-accent-ink': values.accentForeground }" @endif
>
    @if ($preview)
        <div class="bh-customer-cover" x-show="preview.coverUrl">
            <img x-bind:src="preview.coverUrl" alt="Portada de {{ $barbershop->name }}" class="bh-customer-cover__image">
        </div>
        <div class="bh-customer-cover bh-customer-cover--empty" x-show="!preview.coverUrl" aria-hidden="true"></div>
    @elseif ($barbershop->brand_cover_path)
        <div class="bh-customer-cover">
            <img src="{{ route('tenant.branding.asset', [$barbershop, 'cover']) }}" alt="Portada de {{ $barbershop->name }}" class="bh-customer-cover__image">
        </div>
    @else
        <div class="bh-customer-cover bh-customer-cover--empty" aria-hidden="true"></div>
    @endif

    <div class="bh-customer-content">
        <div class="bh-customer-identity">
            @if ($preview)
                <img x-show="preview.logoUrl" x-bind:src="preview.logoUrl" alt="Logo de {{ $barbershop->name }}" class="bh-customer-logo">
                <span x-show="!preview.logoUrl" class="bh-customer-logo bh-customer-logo--empty" aria-hidden="true">{{ str($barbershop->name)->substr(0, 1)->upper() }}</span>
                <h3 class="bh-customer-name" x-text="values.shopName">{{ $barbershop->name }}</h3>
            @elseif ($barbershop->brand_logo_path)
                <img src="{{ route('tenant.branding.asset', [$barbershop, 'logo']) }}" alt="Logo de {{ $barbershop->name }}" class="bh-customer-logo">
                <h3 class="bh-customer-name">{{ $barbershop->name }}</h3>
            @else
                <span class="bh-customer-logo bh-customer-logo--empty" aria-hidden="true">{{ str($barbershop->name)->substr(0, 1)->upper() }}</span>
                <h3 class="bh-customer-name">{{ $barbershop->name }}</h3>
            @endif
        </div>
        @if ($preview)
            <p class="bh-customer-description" x-text="values.description">{{ $tokens['description'] }}</p>
        @else
            <p class="bh-customer-description">{{ $tokens['description'] }}</p>
        @endif

        <div class="bh-customer-swatches" aria-label="Colores de marca">
            <span class="bh-customer-swatch bh-customer-swatch--primary">Color principal</span>
            <span class="bh-customer-swatch bh-customer-swatch--accent">Color de acento</span>
        </div>

        <div class="bh-customer-actions" aria-label="Ejemplo de acciones para clientes">
            <button type="button" class="bh-customer-button bh-customer-button--primary" disabled aria-disabled="true">Reservar cita · demostración</button>
            <button type="button" class="bh-customer-button bh-customer-button--accent" disabled aria-disabled="true">Ver servicios · demostración</button>
        </div>
    </div>
</div>
