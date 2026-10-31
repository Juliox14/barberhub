@props(['title'])

@php
    $accountUser = Auth::user();
    $accountMemberships = $accountUser instanceof \App\Models\User && ! $accountUser->is_platform_admin
        ? \App\Support\TenantDashboard::activeMembershipsFor($accountUser)
        : collect();
@endphp

@if ($accountUser instanceof \App\Models\User && $accountUser->is_platform_admin)
    <x-platform-layout :title="$title" active-page="security">
        {{ $slot }}
    </x-platform-layout>
@elseif ($accountMemberships->count() === 1)
    @php($accountMembership = $accountMemberships->first())
    <x-tenant-layout
        :barbershop="$accountMembership->barbershop"
        :role-label="\App\Support\TenantDashboard::roleLabel($accountMembership)"
        :can-manage-members="in_array($accountMembership->role, [\App\Models\Membership::ROLE_OWNER, \App\Models\Membership::ROLE_ADMIN], true)"
        active-page="account-security"
    >
        {{ $slot }}
    </x-tenant-layout>
@else
    <!DOCTYPE html>
    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="csrf-token" content="{{ csrf_token() }}">
            <meta name="theme-color" content="#0a0e12">
            <title>{{ $title }} · BarberHub</title>
            <link rel="preconnect" href="https://fonts.bunny.net">
            <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet">
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        </head>
        <body class="bh-app font-sans antialiased">
            <div class="min-h-screen">
                <header class="bh-topbar border-b">
                    <div class="mx-auto flex min-h-16 max-w-[1440px] items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                        <x-ui.brand :href="url('/')" />
                        <div class="flex items-center gap-3">
                            <span class="hidden text-sm text-slate-300 sm:inline">{{ $accountUser?->name }}</span>
                            <x-account-settings-menu />
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="rounded-md p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" aria-label="Cerrar sesión">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 17l5-5-5-5m5 5H3m8-8v-1a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v18a2 2 0 0 1-2 2h-6a2 2 0 0 1-2-2v-1"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </header>
                <main class="mx-auto max-w-[1440px] space-y-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <x-credential-security-notice />
                    {{ $slot }}
                </main>
                <x-credits-footer />
            </div>
            <x-form-feedback />
            <x-session-monitor />
        </body>
    </html>
@endif
