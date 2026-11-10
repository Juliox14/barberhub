<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </div>

    <!-- Session Status -->

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <input id="email" class="mt-1 block w-full rounded-xl border border-slate-600 bg-[#0b1015] px-4 py-3 text-sm text-white placeholder:text-slate-500 shadow-inner shadow-black/20 outline-none transition focus:border-amber-300 focus:ring-2 focus:ring-amber-300/25" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" />
        </div>

        <x-recaptcha-challenge :required="$recaptchaRequired ?? false" :available="$recaptchaAvailable ?? false" :site-key="$recaptchaSiteKey ?? null" />

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Email Password Reset Link') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
