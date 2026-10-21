@props(['barbershop', 'roleLabel', 'activePage' => 'dashboard', 'canManageMembers' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $barbershop->name }} · BarberHub</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bh-app font-sans antialiased">
        <div x-data="{ menuOpen: false, sidebarCollapsed: localStorage.getItem('bh-sidebar-collapsed') === 'true' }" :class="sidebarCollapsed ? 'lg:grid-cols-[5.5rem_minmax(0,1fr)]' : 'lg:grid-cols-[250px_minmax(0,1fr)]'" class="min-h-screen lg:grid lg:transition-[grid-template-columns] lg:duration-300 lg:ease-[cubic-bezier(0.16,1,0.3,1)]">
            <aside
                class="bh-sidebar fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r px-4 py-6 transition-[transform] duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] lg:sticky lg:top-0 lg:h-screen lg:w-auto lg:translate-x-0"
                :class="{ 'translate-x-0': menuOpen, 'bh-sidebar--collapsed': sidebarCollapsed }"
            >
                <div class="bh-sidebar__header flex items-center justify-between gap-4 px-3">
                    <x-ui.brand :href="route('tenant.dashboard', $barbershop)" />
                    <button type="button" class="bh-sidebar__collapse-button hidden rounded-md p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 lg:inline-flex" x-on:click="sidebarCollapsed = ! sidebarCollapsed; localStorage.setItem('bh-sidebar-collapsed', sidebarCollapsed)" :aria-label="sidebarCollapsed ? 'Expandir menú lateral' : 'Contraer menú lateral'" :title="sidebarCollapsed ? 'Expandir menú lateral' : 'Contraer menú lateral'">
                        <svg class="h-4 w-4 transition-transform duration-300" :class="sidebarCollapsed ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m12 4-6 6 6 6"/></svg>
                    </button>
                    <button type="button" class="rounded-md p-2 text-slate-400 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 lg:hidden" x-on:click="menuOpen = false" aria-label="Cerrar menú">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
                    </button>
                </div>

                <nav class="mt-10 space-y-1" aria-label="Navegación de barbería">
                    <x-ui.nav-link :href="route('tenant.dashboard', $barbershop)" :active="$activePage === 'dashboard'">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V10Z"/></svg>
                        Inicio
                    </x-ui.nav-link>

                    @if ($canManageMembers)
                        <x-ui.nav-link :href="route('tenant.members.index', $barbershop)" :active="$activePage === 'members'">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            Equipo
                        </x-ui.nav-link>
                    @else
                        <span class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm text-slate-500">Equipo</span>
                    @endif

                    @if ($canManageMembers)
                        <x-ui.nav-link :href="route('tenant.services.index', $barbershop)" :active="$activePage === 'services'">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h10"/><path stroke-linecap="round" stroke-linejoin="round" d="M18 15.5c0 1.38-1.12 2.5-2.5 2.5S13 16.88 13 15.5s1.12-2.5 2.5-2.5S18 14.12 18 15.5Z"/></svg>
                            Servicios
                        </x-ui.nav-link>
                    @else
                        <span class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm text-slate-500">Servicios</span>
                    @endif

                    @if ($canManageMembers)
                        <x-ui.nav-link :href="route('tenant.business-hours.index', $barbershop)" :active="$activePage === 'business-hours'">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2"/></svg>
                            Horarios
                        </x-ui.nav-link>
                    @else
                        <span class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm text-slate-500">Horarios</span>
                    @endif

                    @if ($canManageMembers)
                        <x-ui.nav-link :href="route('tenant.barber-availability.index', $barbershop)" :active="$activePage === 'barber-availability'">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10M4 18h7"/><path stroke-linecap="round" stroke-linejoin="round" d="M18 14v6M15 17h6"/></svg>
                            Disponibilidad
                        </x-ui.nav-link>
                    @else
                        <span class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm text-slate-500">Disponibilidad</span>
                    @endif

                    <x-ui.nav-link :href="route('tenant.appointments.index', $barbershop)" :active="$activePage === 'appointments'">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path stroke-linecap="round" d="M16 3v4M8 3v4M3 10h18"/><path stroke-linecap="round" stroke-linejoin="round" d="m8 15 2 2 5-5"/></svg>
                        Agenda
                    </x-ui.nav-link>

                    <div class="bh-sidebar__upcoming mt-6 border-t border-slate-800 pt-5">
                        <p class="px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Próximamente</p>
                        <div class="mt-2 space-y-1 text-sm text-slate-500">
                            <span class="flex gap-3 px-3 py-2.5">Clientes</span>
                            <span class="flex gap-3 px-3 py-2.5">Inventario</span>
                            <span class="flex gap-3 px-3 py-2.5">Reportes</span>
                            <span class="flex gap-3 px-3 py-2.5">Configuración</span>
                        </div>
                    </div>
                </nav>

                <x-ui.surface as="div" class="mt-auto p-4">
                    <p class="text-sm font-semibold text-white">{{ $barbershop->name }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $barbershop->timezone }}</p>
                </x-ui.surface>
            </aside>

            <div class="min-w-0">
                <header class="bh-topbar sticky top-0 z-30 flex min-h-16 items-center justify-between gap-3 border-b px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3">
                        <button type="button" class="rounded-md p-2 text-slate-300 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 lg:hidden" x-on:click="menuOpen = true" aria-label="Abrir menú">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                        </button>
                        <div>
                            <p class="text-sm font-semibold text-slate-100">{{ $barbershop->name }}</p>
                            <p class="text-xs text-slate-400">{{ $barbershop->timezone }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="hidden text-right sm:block">
                            <p class="text-sm font-semibold text-slate-100">{{ Auth::user()->name }}</p>
                            <p class="text-xs text-slate-400">{{ $roleLabel }}</p>
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
    </body>
</html>
