<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'BarberHub') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gray-100 text-gray-900 antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center px-6 py-12">
            <main class="w-full max-w-3xl rounded-lg bg-white p-8 shadow-sm">
                <p class="text-sm font-semibold uppercase tracking-wide text-gray-500">Proyecto académico</p>

                <h1 class="mt-3 text-3xl font-bold text-gray-900">BarberHub</h1>

                <p class="mt-4 text-base leading-7 text-gray-700">
                    Plataforma web para administrar las operaciones principales de una barbería.
                    Esta primera versión prepara la base técnica del sistema: autenticación, roles,
                    PostgreSQL y una estructura Laravel mantenible para crecer por módulos.
                </p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    @auth
                        <a
                            href="{{ url('/dashboard') }}"
                            class="inline-flex justify-center rounded-md bg-gray-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-gray-700"
                        >
                            Ir al panel
                        </a>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="inline-flex justify-center rounded-md bg-gray-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-gray-700"
                        >
                            Iniciar sesión
                        </a>

                        @if (Route::has('register'))
                            <a
                                href="{{ route('register') }}"
                                class="inline-flex justify-center rounded-md border border-gray-300 px-5 py-3 text-sm font-semibold text-gray-800 transition hover:bg-gray-50"
                            >
                                Crear cuenta
                            </a>
                        @endif
                    @endauth
                </div>
            </main>
        </div>
    </body>
</html>
