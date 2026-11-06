@php
    $homeUrl = \Illuminate\Support\Facades\Route::has('home') ? route('home') : url('/');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · BarberHub</title>
    <style>
        :root {
            color-scheme: dark;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #000;
            color: #f5f5f5;
        }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; background: #000; }
        a { color: inherit; }
        a:focus-visible { outline: 2px solid #f1c96b; outline-offset: 5px; }
        .error {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            align-items: center;
            justify-items: center;
            gap: clamp(12px, 3vh, 28px);
            padding: clamp(32px, 7vw, 88px) 24px;
            overflow: hidden;
        }
        .copy { width: min(100%, 440px); text-align: center; }
        .code {
            display: flex;
            align-items: baseline;
            justify-content: center;
            gap: 0.5rem;
            color: #f5f5f5;
            font-size: clamp(4rem, 9vw, 6rem);
            font-weight: 800;
            letter-spacing: -0.04em;
            line-height: 1;
        }
        .code span { color: #f1c96b; font-size: 0.875rem; font-weight: 600; letter-spacing: 0.12em; }
        h1 {
            margin: 24px 0 0;
            color: #f5f5f5;
            font-size: clamp(1.4rem, 3vw, 2rem);
            font-weight: 700;
            letter-spacing: -0.02em;
            line-height: 1.25;
            text-wrap: balance;
        }
        p { max-width: 38ch; margin: 16px auto 0; color: #c7c7c7; font-size: 1rem; line-height: 1.65; }
        .guidance { margin-top: 10px; color: #a6a6a6; font-size: 0.875rem; }
        .action {
            display: inline-flex;
            min-height: 44px;
            align-items: center;
            justify-content: center;
            margin-top: 32px;
            padding: 0 24px;
            border: 1px solid #f1c96b;
            border-radius: 999px;
            color: #f5f5f5;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            transition: border-color 160ms ease-out, color 160ms ease-out;
        }
        .action:hover { border-color: #f1c96b; color: #f1c96b; }
        .illustration {
            display: grid;
            width: min(100%, 540px);
            place-items: center;
        }
        .illustration img {
            display: block;
            width: 100%;
            height: auto;
            max-height: 42vh;
            object-fit: contain;
            filter: drop-shadow(0 0 22px rgb(215 169 65 / 12%));
        }
        @media (min-width: 760px) {
            .error {
                grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
                gap: clamp(16px, 4vw, 64px);
                padding: 48px clamp(36px, 8vw, 120px);
            }
            .copy { justify-self: end; }
            .illustration { justify-self: start; }
            .illustration img { max-height: 72vh; }
        }
        @media (max-width: 759px) {
            .error { align-content: center; }
            .illustration { width: min(100%, 390px); }
            .illustration img { max-height: 34vh; }
        }
        @media (prefers-reduced-motion: reduce) {
            .action { transition: none; }
        }
    </style>
</head>
<body>
    <main class="error" id="main-content">
        <section class="copy" aria-labelledby="error-title">
            <span class="code" aria-hidden="true">{{ $code }}<span>-error</span></span>
            <h1 id="error-title">{{ $title }}</h1>
            <p>{{ $message }}</p>
            <p class="guidance">{{ $guidance }}</p>
            <a class="action" href="{{ $homeUrl }}">{{ __('errors.home') }}</a>
        </section>
        <div class="illustration">
            <img src="{{ asset('images/errors/logo-disconnected.png') }}" alt="Logotipo de BarberHub con el cable desconectado">
        </div>
    </main>
</body>
</html>
