<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#080a0d">
        <title>BarberHub — Gestiona tu barbería</title>
        <meta name="description" content="Organiza tus citas, administra tu equipo y ofrece una mejor experiencia a tus clientes. Todo desde un solo lugar.">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/css/landing.css', 'resources/js/landing.js'])
    </head>
    <body class="bh-landing" data-bh-landing>
        <a class="bh-landing__skip-link" href="#contenido">Saltar al contenido</a>

        <header class="bh-landing__header">
            <nav class="bh-landing__nav" aria-label="Navegación principal">
                <a class="bh-landing__brand" href="#inicio" aria-label="BarberHub, inicio">
                    <img src="{{ asset('images/landing-logo.webp') }}" width="42" height="42" alt="">
                    <span>BarberHub</span>
                </a>

                <div class="bh-landing__nav-links">
                    <a href="#inicio">Inicio</a>
                    <a href="#funciones">Funciones</a>
                    <a href="#como-funciona">Cómo funciona</a>
                </div>

                <div class="bh-landing__nav-actions">
                    @guest
                        <a data-landing-header-login href="{{ route('login') }}">Iniciar sesión</a>
                        @if (Route::has('register'))
                            <a class="bh-landing__button" href="{{ route('register') }}">Crear cuenta</a>
                        @endif
                    @else
                        <a class="bh-landing__button" href="{{ url('/dashboard') }}">Ir al panel</a>
                    @endguest
                </div>

                <details class="bh-landing__menu">
                    <summary aria-label="Navegación del sitio">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
                    </summary>
                    <div class="bh-landing__menu-panel">
                        <a href="#inicio">Inicio</a>
                        <a href="#funciones">Funciones</a>
                        <a href="#como-funciona">Cómo funciona</a>
                        <div class="bh-landing__menu-auth">
                            @guest
                                <a data-landing-menu-auth href="{{ route('login') }}">Iniciar sesión</a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}">Crear cuenta</a>
                                @endif
                            @else
                                <a href="{{ url('/dashboard') }}">Ir al panel</a>
                            @endguest
                        </div>
                    </div>
                </details>
            </nav>
        </header>

        <main id="contenido">
            <section class="bh-landing__hero" id="inicio" data-landing-hero aria-labelledby="hero-title">
                <picture class="bh-landing__backdrop" data-landing-background>
                    <source
                        type="image/webp"
                        srcset="{{ asset('images/landing-background-960.webp') }} 960w, {{ asset('images/landing-background-1672.webp') }} 1672w"
                        sizes="100vw"
                    >
                    <img src="{{ asset('images/landing_background.png') }}" width="1672" height="941" alt="" fetchpriority="high">
                </picture>
                <div class="bh-landing__shade" aria-hidden="true"></div>
                <div class="bh-landing__dim" data-landing-dim aria-hidden="true"></div>

                <div class="bh-landing__hero-inner">
                    <div class="bh-landing__chair-stage">
                        <picture class="bh-landing__chair-picture">
                            <source
                                type="image/webp"
                                srcset="{{ asset('images/landing-chair-720.webp') }} 720w, {{ asset('images/landing-chair-1122.webp') }} 1122w"
                                sizes="(max-width: 63.999rem) 80vw, 46vw"
                            >
                            <img
                                class="bh-landing__chair"
                                data-landing-chair
                                src="{{ asset('images/silla_landing.png') }}"
                                width="1122"
                                height="1402"
                                alt="Silla de barbería negra de cuero y detalles dorados"
                                fetchpriority="high"
                            >
                        </picture>
                    </div>

                    <div class="bh-landing__copy-stage">
                        <p class="bh-landing__intro" data-landing-intro aria-hidden="true">BarberHub</p>
                        <div class="bh-landing__copy" data-landing-copy>
                            <p class="bh-landing__eyebrow">BarberHub</p>
                            <h1 class="bh-landing__title" id="hero-title"><span class="bh-landing__title-line">Lleva tu barbería al</span><span class="bh-landing__title-accent">siguiente nivel</span></h1>
                            <p class="bh-landing__description">Organiza tus citas, administra tu equipo y ofrece una mejor experiencia a tus clientes. Todo desde un solo lugar.</p>
                            <div class="bh-landing__hero-actions">
                            @guest
                                @if (Route::has('register'))
                                    <a class="bh-landing__button" href="{{ route('register') }}">
                                        Comenzar ahora
                                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.5 10h13m-5-5 5 5-5 5" /></svg>
                                    </a>
                                @endif
                            @else
                                <a class="bh-landing__button" href="{{ url('/dashboard') }}">
                                    Comenzar ahora
                                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.5 10h13m-5-5 5 5-5 5" /></svg>
                                </a>
                            @endguest
                            <a class="bh-landing__button bh-landing__button--secondary" href="#funciones">Conocer BarberHub</a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bh-landing__features" id="funciones" data-landing-features aria-labelledby="features-title">
                <div class="bh-landing__features-intro" id="como-funciona">
                    <h2 class="bh-landing__features-title" id="features-title">Todo lo que necesitas para gestionar tu barbería.</h2>
                    <p class="bh-landing__features-description">Las herramientas esenciales para mantener tu negocio organizado y ofrecer una mejor experiencia a tus clientes.</p>
                </div>

                <div class="bh-landing__feature-grid">
                    <article class="bh-landing__feature" data-landing-feature>
                        <span class="bh-landing__feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="16" rx="2" /><path d="M7.5 3v4M16.5 3v4M3.5 9.5h17M8 13h.01M12 13h.01M16 13h.01M8 17h.01M12 17h.01" /></svg>
                        </span>
                        <h3>Agenda inteligente</h3>
                        <p>Organiza citas y consulta la disponibilidad de tus barberos.</p>
                    </article>

                    <article class="bh-landing__feature" data-landing-feature>
                        <span class="bh-landing__feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5" /><path d="M2.8 20c.5-3.6 2.5-5.5 6.2-5.5 2.1 0 3.7.6 4.8 1.8M17 8v8m-4-4h8M16.5 19.5h4" /></svg>
                        </span>
                        <h3>Gestión de equipo</h3>
                        <p>Administra barberos, servicios y horarios.</p>
                    </article>

                    <article class="bh-landing__feature" data-landing-feature>
                        <span class="bh-landing__feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5" /><path d="M2.8 20c.5-3.6 2.5-5.5 6.2-5.5 2 0 3.6.6 4.7 1.7M16 11.5h5M18.5 9v5" /></svg>
                        </span>
                        <h3>Control de clientes</h3>
                        <p>Consulta la información y el historial de tus clientes.</p>
                    </article>

                    <article class="bh-landing__feature" data-landing-feature>
                        <span class="bh-landing__feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M4 20V11m5 9V5m5 15v-7m5 7V8" /><path d="M2.5 20.5h19" /></svg>
                        </span>
                        <h3>Reportes y estadísticas</h3>
                        <p>Conoce la actividad y el rendimiento de tu barbería.</p>
                    </article>
                </div>
            </section>
        </main>
    </body>
</html>
