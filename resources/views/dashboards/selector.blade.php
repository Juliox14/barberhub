<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Selecciona una barbería</h2>
    </x-slot>

    <main class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($memberships as $membership)
                    <a href="{{ route('tenant.dashboard', $membership->barbershop) }}" class="block bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 hover:border-gray-300 transition">
                        <article class="p-6 space-y-3">
                            <p class="text-sm font-medium text-gray-500">{{ \App\Support\TenantDashboard::roleLabel($membership) }}</p>
                            <h3 class="text-xl font-semibold text-gray-900">{{ $membership->barbershop->name }}</h3>
                            <p class="text-gray-600">Entrar al panel de esta barbería.</p>
                        </article>
                    </a>
                @endforeach
            </div>
        </div>
    </main>
</x-app-layout>
