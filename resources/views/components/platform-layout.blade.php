@props(['title' => 'BarberHub', 'activePage' => 'dashboard'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-[#0a0e12] font-sans text-slate-100 antialiased selection:bg-amber-300 selection:text-[#17120a]">
        <div x-data="{ menuOpen: false }" class="min-h-screen lg:grid lg:grid-cols-[250px_minmax(0,1fr)]">
            <aside class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-slate-800 bg-[#0d1217] px-4 py-6 transition duration-200 lg:sticky lg:top-0 lg:h-screen lg:w-auto lg:translate-x-0" :class="{ 'translate-x-0': menuOpen }" aria-label="Navegación de plataforma">
                <div class="flex items-center justify-between gap-4 px-3">
                    <a href="{{ route('platform.dashboard') }}" class="flex items-center gap-3 rounded-md outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                        <img src="{{ asset('images/logo.png') }}" alt="" class="h-10 w-10 object-contain" />
                        <span class="text-2xl font-bold tracking-[-0.04em] text-white">BarberHub</span>
                    </a>
                    <button type="button" class="rounded-md p-2 text-slate-400 hover:text-white focus-visible:ring-2 focus-visible:ring-amber-300 lg:hidden" x-on:click="menuOpen = false" aria-label="Cerrar menú">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
                    </button>
                </div>

                <nav class="mt-10 space-y-1" aria-label="Navegación principal">
                    <a href="{{ route('platform.dashboard') }}" @class([
                        'flex items-center gap-3 rounded-lg px-3 py-3 text-sm transition focus-visible:ring-2 focus-visible:ring-amber-300',
                        'bg-amber-300/10 font-semibold text-amber-200 ring-1 ring-inset ring-amber-300/20' => $activePage === 'dashboard',
                        'font-medium text-slate-300 hover:bg-slate-800 hover:text-white' => $activePage !== 'dashboard',
                    ])>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V10Z"/></svg>
                        Dashboard global
                    </a>
                    <a href="{{ route('platform.barbershops.index') }}" @class([
                        'flex items-center gap-3 rounded-lg px-3 py-3 text-sm transition focus-visible:ring-2 focus-visible:ring-amber-300',
                        'bg-amber-300/10 font-semibold text-amber-200 ring-1 ring-inset ring-amber-300/20' => $activePage === 'barbershops',
                        'font-medium text-slate-300 hover:bg-slate-800 hover:text-white' => $activePage !== 'barbershops',
                    ])>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 21V9l8-5 8 5v12M2 21h20M8 21v-5h8v5M8 10h.01M12 10h.01M16 10h.01"/></svg>
                        Barberías
                    </a>
                </nav>

                <div class="mt-8 border-t border-slate-800 pt-6">
                    <p class="px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Próximamente</p>
                    <div class="mt-3 space-y-1 text-sm text-slate-500" aria-label="Módulos aún no disponibles">
                        <span class="flex items-center gap-3 px-3 py-2.5"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>Miembros</span>
                        <span class="flex items-center gap-3 px-3 py-2.5"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path stroke-linecap="round" d="M7 3v4m10-4v4M3 11h18"/></svg>Citas</span>
                        <span class="flex items-center gap-3 px-3 py-2.5"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4 6v5c0 5 3.4 8.8 8 10 4.6-1.2 8-5 8-10V6l-8-3Z"/></svg>Seguridad</span>
                    </div>
                </div>

                <div class="mt-auto rounded-xl border border-slate-700/80 bg-slate-900/60 p-4">
                    <p class="text-sm font-semibold text-white">Administración global</p>
                    <p class="mt-1 text-xs leading-5 text-slate-400">Gestiona las barberías registradas en BarberHub.</p>
                </div>
            </aside>

            <div class="min-w-0">
                <header class="sticky top-0 z-30 flex min-h-16 items-center justify-between gap-4 border-b border-slate-800 bg-[#0a0e12]/95 px-4 backdrop-blur sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3">
                        <button type="button" class="rounded-md p-2 text-slate-300 hover:bg-slate-800 focus-visible:ring-2 focus-visible:ring-amber-300 lg:hidden" x-on:click="menuOpen = true" aria-label="Abrir menú">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                        </button>
                        <div class="hidden items-center gap-2 text-sm font-medium text-slate-300 sm:flex">
                            <svg class="h-5 w-5 text-amber-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M3 12h18M12 3c2.4 2.5 3.6 5.5 3.6 9S14.4 18.5 12 21c-2.4-2.5-3.6-5.5-3.6-9S9.6 5.5 12 3Z"/></svg>
                            Vista global
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="hidden text-right sm:block">
                            <p class="text-sm font-semibold text-slate-100">{{ Auth::user()->name }}</p>
                            <p class="text-xs text-slate-400">Superadministrador</p>
                        </div>
                        <div class="flex h-9 w-9 items-center justify-center rounded-full border border-amber-300/30 bg-amber-300/10 text-sm font-bold text-amber-200" aria-hidden="true">{{ str(Auth::user()->name)->substr(0, 1)->upper() }}</div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-md p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white focus-visible:ring-2 focus-visible:ring-amber-300" aria-label="Cerrar sesión">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 17l5-5-5-5m5 5H3m8-8v-1a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v18a2 2 0 0 1-2 2h-6a2 2 0 0 1-2-2v-1"/></svg>
                            </button>
                        </form>
                    </div>
                </header>

                <main class="p-4 sm:p-6 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
