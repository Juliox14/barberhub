<section id="change-password" class="scroll-mt-24">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" :value="__('Current Password')" />
            <x-password-field id="update_password_current_password" tone="light" class="mt-1">
                <x-text-input id="update_password_current_password" name="current_password" type="password" x-bind:type="passwordVisible ? 'text' : 'password'" class="block w-full pr-12" autocomplete="current-password" />
            </x-password-field>
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('New Password')" />
            <x-password-field id="update_password_password" tone="light" class="mt-1">
                <x-text-input id="update_password_password" name="password" type="password" x-bind:type="passwordVisible ? 'text' : 'password'" class="block w-full pr-12" autocomplete="new-password" />
            </x-password-field>
            <x-password-requirements tone="light" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
            <x-password-field id="update_password_password_confirmation" tone="light" class="mt-1">
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" x-bind:type="passwordVisible ? 'text' : 'password'" class="block w-full pr-12" autocomplete="new-password" />
            </x-password-field>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

        </div>
    </form>
</section>
