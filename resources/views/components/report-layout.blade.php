@props(['barbershop', 'roleLabel', 'title', 'description'])

<x-tenant-layout :barbershop="$barbershop" :role-label="$roleLabel" active-page="reports" :can-manage-members="true">
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="space-y-2">
            <h1 class="text-2xl font-bold tracking-[-0.03em] text-white sm:text-3xl">{{ $title }}</h1>
            <p class="max-w-3xl text-sm leading-6 text-slate-400">{{ $description }}</p>
        </header>
        {{ $slot }}
    </div>
</x-tenant-layout>
