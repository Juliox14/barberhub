<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Crear barbería</h2>
            <a href="{{ route('platform.barbershops.index') }}" class="text-sm font-semibold text-gray-600 hover:text-gray-900">
                Volver a barberías
            </a>
        </div>
    </x-slot>

    <main class="py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <section class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('platform.barbershops.store') }}" class="p-6 space-y-6">
                    @csrf

                    <div>
                        <p class="text-sm font-medium text-gray-500">Alta manual</p>
                        <h3 class="text-2xl font-semibold text-gray-900">Nueva barbería</h3>
                        <p class="mt-1 text-sm text-gray-600">El correo propietario debe pertenecer a una persona usuaria existente.</p>
                    </div>

                    <div>
                        <x-input-label for="name" value="Nombre" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus />
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <x-input-label for="slug" value="Slug" />
                        <x-text-input id="slug" name="slug" type="text" class="mt-1 block w-full" :value="old('slug')" required placeholder="barberia-central" />
                        <p class="mt-1 text-xs text-gray-500">Usa minúsculas, números y guiones. Debe ser único.</p>
                        <x-input-error class="mt-2" :messages="$errors->get('slug')" />
                    </div>

                    <div>
                        <x-input-label for="timezone" value="Zona horaria" />
                        <x-text-input id="timezone" name="timezone" type="text" class="mt-1 block w-full" :value="old('timezone', 'America/Bogota')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('timezone')" />
                    </div>

                    <div>
                        <x-input-label for="status" value="Estado" />
                        <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="active" @selected(old('status', 'active') === 'active')>Activa</option>
                            <option value="inactive" @selected(old('status') === 'inactive')>Inactiva</option>
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('status')" />
                    </div>

                    <div>
                        <x-input-label for="owner_email" value="Correo de la persona propietaria" />
                        <x-text-input id="owner_email" name="owner_email" type="email" class="mt-1 block w-full" :value="old('owner_email')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('owner_email')" />
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('platform.barbershops.index') }}" class="text-sm font-semibold text-gray-600 hover:text-gray-900">Cancelar</a>
                        <x-primary-button>Crear barbería</x-primary-button>
                    </div>
                </form>
            </section>
        </div>
    </main>
</x-app-layout>
