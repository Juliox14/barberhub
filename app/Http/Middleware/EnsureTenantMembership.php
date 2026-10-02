<?php

namespace App\Http\Middleware;

use App\Models\Barbershop;
use App\Models\Membership;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        if ($user->is_platform_admin) {
            return $next($request);
        }

        $barbershop = $request->route('barbershop');

        if (! $barbershop instanceof Barbershop) {
            abort(403);
        }

        $hasMembership = $user->memberships()
            ->where('barbershop_id', $barbershop->id)
            ->where('status', Membership::STATUS_ACTIVE)
            ->exists();

        if (! $hasMembership) {
            abort(403);
        }

        return $next($request);
    }
}
