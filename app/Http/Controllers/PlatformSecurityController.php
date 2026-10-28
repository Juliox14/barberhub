<?php

namespace App\Http\Controllers;

use App\Events\PlatformSecurityAction;
use App\Http\Requests\PlatformModuleContainmentRequest;
use App\Http\Requests\PlatformModuleReleaseRequest;
use App\Http\Requests\PlatformSessionRevocationRequest;
use App\Models\Barbershop;
use App\Models\ModuleContainment as ModuleContainmentRecord;
use App\Models\User;
use App\Support\ModuleContainment;
use App\Support\SingleDeviceSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class PlatformSecurityController extends Controller
{
    public function index(): View
    {
        return view('platform.security', [
            'users' => User::query()->orderBy('email')->get(['id', 'name', 'email']),
            'barbershops' => Barbershop::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'containments' => ModuleContainmentRecord::query()->with('barbershop')->get()->keyBy(
                fn (ModuleContainmentRecord $containment): string => $containment->barbershop_id.'-'.$containment->module,
            ),
            'modules' => ModuleContainmentRecord::modules(),
        ]);
    }

    public function revokeSession(
        PlatformSessionRevocationRequest $request,
        User $user,
        SingleDeviceSession $sessions,
    ): RedirectResponse {
        $reason = trim($request->validated('reason'));
        $sessions->revoke($user);

        PlatformSecurityAction::dispatch(
            'session_revoked',
            (int) $request->user()->getKey(),
            (int) $user->getKey(),
            null,
            null,
            $reason,
            null,
        );

        return redirect()->route('platform.security.index')->with('status', 'Sesiones de la cuenta revocadas.');
    }

    public function suspendModule(
        PlatformModuleContainmentRequest $request,
        Barbershop $barbershop,
        ModuleContainment $containments,
    ): RedirectResponse {
        $validated = $request->validated();
        $containment = $containments->suspend($barbershop, $validated['module'], trim($validated['reason']));

        if ($containment !== null) {
            PlatformSecurityAction::dispatch(
                'module_suspended',
                (int) $request->user()->getKey(),
                null,
                (int) $barbershop->getKey(),
                $containment->module,
                $containment->reason,
                $containment->expires_at->toIso8601String(),
            );
        }

        return redirect()->route('platform.security.index')->with(
            'status',
            $containment === null
                ? 'El módulo ya tiene una suspensión vigente; su plazo no se modificó.'
                : 'Módulo suspendido durante 15 minutos.',
        );
    }

    public function releaseModule(
        PlatformModuleReleaseRequest $request,
        Barbershop $barbershop,
        ModuleContainment $containments,
    ): RedirectResponse {
        $validated = $request->validated();
        $containment = $containments->release($barbershop, $validated['module'], trim($validated['reason']));

        if ($containment !== null) {
            PlatformSecurityAction::dispatch(
                'module_released',
                (int) $request->user()->getKey(),
                null,
                (int) $barbershop->getKey(),
                $containment->module,
                $containment->release_reason,
                null,
            );
        }

        return redirect()->route('platform.security.index')->with(
            'status',
            $containment === null
                ? 'No hay una suspensión vigente para levantar.'
                : 'Restricción del módulo levantada.',
        );
    }
}
