<?php

namespace App\Observers;

use App\Models\User;

final class PasswordCredentialObserver
{
    public function creating(User $user): void
    {
        if ($user->password_changed_at === null) {
            $user->password_changed_at = now();
        }
    }

    public function updating(User $user): void
    {
        if ($user->isDirty('password')) {
            $user->password_changed_at = now();
        }
    }
}
