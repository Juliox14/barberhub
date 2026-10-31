<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\PasswordCredentialObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password as PasswordRule;

final class PasswordPolicyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        User::observe(PasswordCredentialObserver::class);

        PasswordRule::defaults(fn (): PasswordRule => PasswordRule::min(12)
            ->mixedCase()
            ->numbers()
            ->symbols());
    }
}
