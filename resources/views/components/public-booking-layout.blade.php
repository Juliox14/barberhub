@props(['barbershop', 'branding', 'title' => 'Reserva tu cita'])
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · {{ $barbershop->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/pwa.js'])
</head>
<body class="bh-public" style="--bh-brand-primary: {{ $branding['primary'] }}; --bh-brand-primary-ink: {{ $branding['primary_foreground'] }}; --bh-brand-accent: {{ $branding['accent'] }}; --bh-brand-accent-ink: {{ $branding['accent_foreground'] }}; --bh-brand-surface: {{ $branding['surface'] }};">
    <header class="bh-public-header">
        <a href="{{ route('public.booking.create', $barbershop) }}" class="bh-public-identity">
            @if ($barbershop->brand_logo_path)
                <img src="{{ route('tenant.branding.asset', [$barbershop, 'logo']) }}" alt="" width="56" height="56">
            @endif
            <span>{{ $barbershop->name }}</span>
        </a>
        <nav class="bh-public-header-actions" aria-label="Cuenta y reservas">
            @if (request()->routeIs('public.booking.create') && auth()->user()?->customers()->where('barbershop_id', $barbershop->id)->exists())
                <a href="{{ route('customer.profile.show', $barbershop) }}" class="bh-public-link">Mi perfil</a>
            @endif
            <a href="{{ route('login') }}" class="bh-public-link">{{ auth()->check() ? 'Mi cuenta' : 'Iniciar sesión' }}</a>
        </nav>
    </header>
    <main id="main-content" class="bh-public-main">
        @if ($errors->any())
            <section role="alert" class="bh-public-error" aria-label="Revisa estos datos">
                <h2>No pudimos continuar</h2>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </section>
        @endif
        {{ $slot }}
    </main>
    <footer class="bh-public-footer">Reservas con BarberHub · Horarios de {{ $barbershop->timezone }}</footer>
</body>
</html>
