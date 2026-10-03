<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Panel de {{ $barbershop->name }}</h2>
    </x-slot>

    <main class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <section class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-3">
                    <p class="text-sm font-medium text-gray-500">Rol: {{ $roleLabel }}</p>
                    <h3 class="text-2xl font-semibold">Bienvenido al panel de la barbería</h3>
                    <p>{{ $roleCopy }}</p>
                </div>
            </section>
        </div>
    </main>
</x-app-layout>
