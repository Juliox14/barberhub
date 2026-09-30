<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

final class RoleDashboard
{
    /**
     * @return array<string, string>
     */
    public static function routeNamesByRole(): array
    {
        return [
            'admin' => 'admin.dashboard',
            'barber' => 'barber.dashboard',
            'client' => 'client.dashboard',
        ];
    }

    public static function routeNameFor(?Authenticatable $user): ?string
    {
        return self::routeNamesByRole()[$user instanceof User ? $user->role : null] ?? null;
    }

    public static function labelFor(?Authenticatable $user): ?string
    {
        return match ($user instanceof User ? $user->role : null) {
            'admin' => 'Panel de administración',
            'barber' => 'Panel de barbero',
            'client' => 'Panel de cliente',
            default => null,
        };
    }
}
