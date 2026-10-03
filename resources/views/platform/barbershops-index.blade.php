<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Barberías</h2>
            <a href="{{ route('platform.barbershops.create') }}" class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2">
                Nueva barbería
            </a>
        </div>
    </x-slot>

    <main class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm font-medium text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <section class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Administración de plataforma</p>
                        <h3 class="text-2xl font-semibold">Barberías registradas</h3>
                        <p class="mt-1 text-sm text-gray-600">Crea y revisa las barberías disponibles en BarberHub.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left font-semibold text-gray-700">
                                <tr>
                                    <th class="px-4 py-3">Nombre</th>
                                    <th class="px-4 py-3">Slug</th>
                                    <th class="px-4 py-3">Zona horaria</th>
                                    <th class="px-4 py-3">Estado</th>
                                    <th class="px-4 py-3">Miembros</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($barbershops as $barbershop)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900">{{ $barbershop->name }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ $barbershop->slug }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ $barbershop->timezone }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800">
                                                {{ $barbershop->status === \App\Models\Barbershop::STATUS_ACTIVE ? 'Activa' : 'Inactiva' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600">{{ $barbershop->memberships_count }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">Todavía no hay barberías registradas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $barbershops->links() }}
                </div>
            </section>
        </div>
    </main>
</x-app-layout>
