<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Panel de plataforma</h2>
    </x-slot>

    <main class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <section class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <div class="space-y-2">
                        <p class="text-sm font-medium text-gray-500">Administrador de plataforma</p>
                        <h3 class="text-2xl font-semibold">Vista general de BarberHub</h3>
                        <p>Desde aquí puedes supervisar barberías, miembros y operaciones globales de la plataforma.</p>
                    </div>

                    <a href="{{ route('platform.barbershops.index') }}" class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2">
                        Gestionar barberías
                    </a>
                </div>
            </section>
        </div>
    </main>
</x-app-layout>
