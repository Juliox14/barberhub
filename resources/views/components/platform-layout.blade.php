@props(['title' => 'BarberHub', 'activePage' => 'dashboard'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bh-app font-sans antialiased">
        <div x-data="{ menuOpen: false, sidebarCollapsed: localStorage.getItem('bh-sidebar-collapsed') === 'true' }" :class="sidebarCollapsed ? 'lg:grid-cols-[5.5rem_minmax(0,1fr)]' : 'lg:grid-cols-[250px_minmax(0,1fr)]'" class="min-h-screen lg:grid lg:transition-[grid-template-columns] lg:duration-300 lg:ease-[cubic-bezier(0.16,1,0.3,1)]">
            <aside
                class="bh-sidebar fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r px-4 py-6 transition-[transform] duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] lg:sticky lg:top-0 lg:h-screen lg:w-auto lg:translate-x-0"
                :class="{ 'translate-x-0': menuOpen, 'bh-sidebar--collapsed': sidebarCollapsed }"
                aria-label="Navegación de plataforma"
            >
                <div class="bh-sidebar__header flex items-center justify-between gap-4 px-3">
                    <x-ui.brand :href="route('platform.dashboard')" />
                    <button type="button" class="bh-sidebar__collapse-button hidden rounded-md p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 lg:inline-flex" x-on:click="sidebarCollapsed = ! sidebarCollapsed; localStorage.setItem('bh-sidebar-collapsed', sidebarCollapsed)" :aria-label="sidebarCollapsed ? 'Expandir menú lateral' : 'Contraer menú lateral'" :title="sidebarCollapsed ? 'Expandir menú lateral' : 'Contraer menú lateral'">
                        <svg class="h-4 w-4 transition-transform duration-300" :class="sidebarCollapsed ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m12 4-6 6 6 6"/></svg>
                    </button>
                    <button type="button" class="rounded-md p-2 text-slate-400 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 lg:hidden" x-on:click="menuOpen = false" aria-label="Cerrar menú">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
                    </button>
                </div>

                <nav class="mt-10 space-y-1" aria-label="Navegación principal">
                    <x-ui.nav-link :href="route('platform.dashboard')" :active="$activePage === 'dashboard'">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V10Z"/></svg>
                        Dashboard global
                    </x-ui.nav-link>
                    <x-ui.nav-link :href="route('platform.barbershops.index')" :active="$activePage === 'barbershops'">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 21V9l8-5 8 5v12M2 21h20M8 21v-5h8v5M8 10h.01M12 10h.01M16 10h.01"/></svg>
                        Barberías
                    </x-ui.nav-link>
                    <x-ui.nav-link :href="route('platform.security-audit-logs.index')" :active="$activePage === 'security-audit-logs'">
                        Bitácora y alertas
                    </x-ui.nav-link>
                    <x-ui.nav-link :href="route('platform.security.index')" :active="$activePage === 'security'">
                        Contención de seguridad
                    </x-ui.nav-link>
                </nav>

                <div class="bh-sidebar__upcoming mt-8 border-t border-slate-800 pt-6">
                    <p class="px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Próximamente</p>
                    <div class="mt-3 space-y-1 text-sm text-slate-500" aria-label="Módulos aún no disponibles">
                        <span class="flex items-center gap-3 px-3 py-2.5"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>Miembros</span>
                        <span class="flex items-center gap-3 px-3 py-2.5"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path stroke-linecap="round" d="M7 3v4m10-4v4M3 11h18"/></svg>Citas</span>
                    </div>
                </div>

                <x-ui.surface as="div" class="mt-auto p-4">
                    <p class="text-sm font-semibold text-white">Administración global</p>
                    <p class="mt-1 text-xs leading-5 text-slate-400">Gestiona las barberías registradas en BarberHub.</p>
                </x-ui.surface>
            </aside>

            <div class="min-w-0">
                <header class="bh-topbar sticky top-0 z-30 flex min-h-16 items-center justify-between gap-4 border-b px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3">
                        <button type="button" class="rounded-md p-2 text-slate-300 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 lg:hidden" x-on:click="menuOpen = true" aria-label="Abrir menú">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                        </button>
                        <div class="hidden items-center gap-2 text-sm font-medium text-slate-300 sm:flex">
                            <svg class="h-5 w-5 text-amber-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M3 12h18M12 3c2.4 2.5 3.6 5.5 3.6 9S14.4 18.5 12 21c-2.4-2.5-3.6-5.5-3.6-9S9.6 5 12 3Z"/></svg>
                            Vista global
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="hidden text-right sm:block">
                            <p class="text-sm font-semibold text-slate-100">{{ Auth::user()->name }}</p>
                            <p class="text-xs text-slate-400">Superadministrador</p>
                        </div>
                        <div class="bh-user-avatar" aria-hidden="true">{{ str(Auth::user()->name)->substr(0, 1)->upper() }}</div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-md p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" aria-label="Cerrar sesión">
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
        <x-form-feedback />
        <x-session-monitor />
    </body>
</html>
