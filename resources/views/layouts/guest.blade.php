<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'BarberHub') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-[#080a0d] font-sans text-[#f7f3eb] antialiased selection:bg-amber-300 selection:text-[#17120a]">
        <div class="min-h-screen lg:grid lg:grid-cols-[minmax(0,1.1fr)_minmax(430px,0.9fr)]">
            <aside class="relative hidden min-h-screen overflow-hidden lg:flex" aria-label="Acerca de BarberHub">
                <img src="{{ asset('images/login_background.png') }}" alt="Interior de una barbería" class="absolute inset-0 h-full w-full object-cover" />
                <div class="absolute inset-0 bg-[#06080a]/72"></div>
                <div class="absolute inset-y-0 right-0 w-2/5 bg-gradient-to-l from-[#080a0d] via-[#080a0d]/45 to-transparent"></div>

                <div class="relative z-10 flex w-full flex-col justify-between p-10 xl:p-14">
                    <a href="{{ url('/') }}" class="inline-flex w-fit items-center gap-3 rounded-md outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-4 focus-visible:ring-offset-[#080a0d]">
                        <img src="{{ asset('images/logo.png') }}" alt="" class="h-24 w-24 object-contain" />
                        <span>
                            <span class="block text-3xl font-bold tracking-[-0.035em] text-white">BarberHub</span>
                            <span class="mt-0.5 block text-[10px] font-semibold uppercase tracking-[0.31em] text-slate-300">Plataforma de gestión</span>
                        </span>
                    </a>

                    <div class="max-w-xl">
                        <h1 class="max-w-lg text-5xl font-extrabold leading-[0.98] tracking-[-0.045em] text-white xl:text-6xl">
                            Tu barbería,<br>
                            <span class="text-amber-300">en otro nivel.</span>
                        </h1>
                        <p class="mt-6 max-w-lg text-lg leading-8 text-slate-200">
                            Organiza la operación de tu barbería, tu equipo y tus clientes en un solo lugar.
                        </p>

                        <dl class="mt-12 grid max-w-2xl grid-cols-3 gap-6 border-t border-white/15 pt-6 text-sm">
                            <div>
                                <dt class="font-semibold text-amber-200">Agenda clara</dt>
                                <dd class="mt-2 leading-5 text-slate-300">Citas y jornada siempre a la vista.</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-amber-200">Equipo conectado</dt>
                                <dd class="mt-2 leading-5 text-slate-300">Información para operar con orden.</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-amber-200">Clientes al centro</dt>
                                <dd class="mt-2 leading-5 text-slate-300">Una experiencia más cercana y simple.</dd>
                            </div>
                        </dl>
                    </div>

                    <p class="text-sm text-slate-300">Una plataforma para barberías modernas.</p>
                </div>
            </aside>

            <main class="flex min-h-screen items-center justify-center px-5 py-8 sm:px-8 lg:px-10 xl:px-14">
                <div class="w-full max-w-md">
                    <a href="{{ url('/') }}" class="mb-10 inline-flex items-center gap-3 rounded-md outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-4 focus-visible:ring-offset-[#080a0d] lg:hidden">
                        <img src="{{ asset('images/logo.png') }}" alt="" class="h-10 w-10 object-contain" />
                        <span class="text-2xl font-bold tracking-[-0.035em] text-white">BarberHub</span>
                    </a>

                    <section class="rounded-2xl border border-slate-700/80 bg-[#10151a] p-6 shadow-[0_24px_70px_rgba(0,0,0,0.38)] sm:p-10">
                        <div class="mb-8 hidden items-center justify-center gap-3 lg:flex">
                            <img src="{{ asset('images/logo.png') }}" alt="" class="h-10 w-10 object-contain" />
                            <span class="text-3xl font-bold tracking-[-0.04em] text-white">BarberHub</span>
                        </div>

                        {{ $slot }}
                    </section>

                    <div class="mt-6 grid grid-cols-2 gap-5 px-1 text-left">
                        <div class="flex items-start gap-3 text-slate-400">
                            <svg class="mt-0.5 h-6 w-6 shrink-0 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2.75 20 6v5.25c0 4.9-3.35 8.6-8 10-4.65-1.4-8-5.1-8-10V6l8-3.25Z"/><path stroke-linecap="round" stroke-linejoin="round" d="m8.75 12 2.1 2.1 4.4-4.4"/></svg>
                            <p class="text-xs leading-5">Tus datos están<br>protegidos</p>
                        </div>
                        <div class="flex items-start gap-3 text-slate-400">
                            <svg class="mt-0.5 h-6 w-6 shrink-0 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="4" y="10" width="16" height="10" rx="2"/><path stroke-linecap="round" d="M8 10V7a4 4 0 0 1 8 0v3"/><path stroke-linecap="round" d="M12 14v2"/></svg>
                            <p class="text-xs leading-5">Conexión segura<br>y privada</p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
