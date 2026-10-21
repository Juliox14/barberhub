<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900">Configura tu contraseña</h1>
        <p class="mt-2 text-sm text-gray-600">Al guardar tu contraseña, verificaremos este correo y podrás iniciar sesión en BarberHub.</p>
    </div>

    <form method="POST" action="{{ route('barber-invitations.complete') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">

        <div>
            <x-input-label for="email_display" value="Correo electrónico" />
            <x-text-input id="email_display" class="mt-1 block w-full" type="email" :value="$email" disabled autocomplete="username" />
        </div>

        <div>
            <x-input-label for="password" value="Nueva contraseña" />
            <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required autofocus autocomplete="new-password" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirma la contraseña" />
            <x-text-input id="password_confirmation" class="mt-1 block w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
        </div>


        <div class="flex items-center justify-end">
            <x-primary-button>Configurar contraseña</x-primary-button>
        </div>
    </form>
</x-guest-layout>
