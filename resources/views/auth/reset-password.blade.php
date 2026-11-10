<x-guest-layout>
    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-password-field id="password" tone="light" class="mt-1">
                <x-text-input id="password" class="block w-full pr-12" type="password" x-bind:type="passwordVisible ? 'text' : 'password'" name="password" required autocomplete="new-password" />
            </x-password-field>
            <x-password-requirements />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-password-field id="password_confirmation" tone="light" class="mt-1">
                <x-text-input id="password_confirmation" class="block w-full pr-12"
                                    type="password" x-bind:type="passwordVisible ? 'text' : 'password'"
                                    name="password_confirmation" required autocomplete="new-password" />
            </x-password-field>

        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Reset Password') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
