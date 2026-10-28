<?php

namespace App\Http\Middleware;

use App\Models\Barbershop;
use App\Models\ModuleContainment as ModuleContainmentRecord;
use App\Support\ModuleContainment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureModuleAvailable
{
    public function __construct(private ModuleContainment $containment) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless(array_key_exists($module, ModuleContainmentRecord::modules()), 404);

        $barbershop = $request->route('barbershop');
        abort_unless($barbershop instanceof Barbershop, 404);

        abort_if($this->containment->isSuspended($barbershop, $module), 403);

        return $next($request);
    }
}
