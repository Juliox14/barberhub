<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />

            <x-password-field id="password" tone="light" class="mt-1">
                <x-text-input id="password" class="block w-full pr-12"
                                type="password" x-bind:type="passwordVisible ? 'text' : 'password'"
                                name="password"
                                required autocomplete="current-password" />
            </x-password-field>

        </div>

        <div class="flex justify-end mt-4">
            <x-primary-button>
                {{ __('Confirm') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
