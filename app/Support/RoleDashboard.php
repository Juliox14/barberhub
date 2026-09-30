<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

final class RoleDashboard
{
    public static function routeNameFor(?Authenticatable $user): string
    {
        return match ($user instanceof User ? $user->role : null) {
            'admin' => 'admin.dashboard',
            'barber' => 'barber.dashboard',
            default => 'client.dashboard',
        };
    }
}
