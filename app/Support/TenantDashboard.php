<?php

namespace App\Support;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;

final class TenantDashboard
{
    /**
     * @return array{route: string, parameters: array<string, mixed>, label: string}|null
     */
    public static function destinationFor(?Authenticatable $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        if ($user->is_platform_admin) {
            return [
                'route' => 'platform.dashboard',
                'parameters' => [],
                'label' => 'Panel de plataforma',
            ];
        }

        $memberships = self::activeMembershipsFor($user);

        if ($memberships->count() === 1) {
            /** @var Membership $membership */
            $membership = $memberships->first();

            return [
                'route' => 'tenant.dashboard',
                'parameters' => ['barbershop' => $membership->barbershop],
                'label' => 'Panel de barbería',
            ];
        }

        if ($memberships->count() > 1) {
            return [
                'route' => 'tenant.selector',
                'parameters' => [],
                'label' => 'Mis barberías',
            ];
        }

        return null;
    }

    public static function urlFor(?Authenticatable $user): ?string
    {
        $destination = self::destinationFor($user);

        if ($destination === null) {
            return null;
        }

        return route($destination['route'], $destination['parameters']);
    }

    public static function labelFor(?Authenticatable $user): ?string
    {
        return self::destinationFor($user)['label'] ?? null;
    }

    /**
     * @return Collection<int, Membership>
     */
    public static function activeMembershipsFor(User $user): Collection
    {
        return $user->memberships()
            ->with('barbershop')
            ->where('memberships.status', Membership::STATUS_ACTIVE)
            ->join('barbershops', 'barbershops.id', '=', 'memberships.barbershop_id')
            ->orderBy('barbershops.name')
            ->select('memberships.*')
            ->get();
    }

    public static function membershipFor(User $user, Barbershop $barbershop): ?Membership
    {
        if ($user->is_platform_admin) {
            return null;
        }

        return $user->memberships()
            ->where('barbershop_id', $barbershop->id)
            ->where('status', Membership::STATUS_ACTIVE)
            ->first();
    }

    public static function roleLabel(?Membership $membership): string
    {
        return match ($membership?->role) {
            Membership::ROLE_OWNER => 'Propietario',
            Membership::ROLE_ADMIN => 'Administrador',
            Membership::ROLE_BARBER => 'Barbero',
            default => 'Administrador de plataforma',
        };
    }

    public static function roleCopy(?Membership $membership): string
    {
        return match ($membership?->role) {
            Membership::ROLE_OWNER => 'Gestiona la operación completa, el equipo y la configuración de tu barbería.',
            Membership::ROLE_ADMIN => 'Coordina agenda, clientes y operaciones diarias de la barbería.',
            Membership::ROLE_BARBER => 'Consulta tus citas, clientes asignados y herramientas para atender la jornada.',
            default => 'Puedes supervisar esta barbería como administrador de plataforma.',
        };
    }

    public static function canUseTenantDashboard(User $user, Barbershop $barbershop): bool
    {
        if ($user->is_platform_admin) {
            return true;
        }

        return $user->memberships()
            ->where('barbershop_id', $barbershop->id)
            ->where('status', Membership::STATUS_ACTIVE)
            ->exists();
    }
}
