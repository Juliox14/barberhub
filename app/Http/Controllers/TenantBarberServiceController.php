<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Membership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class TenantBarberServiceController extends Controller
{
    public function selfEdit(Request $request, Barbershop $barbershop): View
    {
        $barber = Barber::query()
            ->where('barbershop_id', $barbershop->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return $this->editView($barbershop, $barber, false);
    }

    public function selfUpdate(Request $request, Barbershop $barbershop): RedirectResponse
    {
        $barber = Barber::query()
            ->where('barbershop_id', $barbershop->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return $this->save($request, $barbershop, $barber, false);
    }

    public function edit(Request $request, Barbershop $barbershop, Barber $barber): View
    {
        $this->ensureBarberBelongsToShop($barbershop, $barber);
        $this->authorizeManager($request, $barbershop);

        return $this->editView($barbershop, $barber, true);
    }

    public function update(Request $request, Barbershop $barbershop, Barber $barber): RedirectResponse
    {
        $this->ensureBarberBelongsToShop($barbershop, $barber);
        $this->authorizeManager($request, $barbershop);

        return $this->save($request, $barbershop, $barber, true);
    }

    private function editView(Barbershop $barbershop, Barber $barber, bool $manager): View
    {
        return view('tenant.barber-services-edit', [
            'barbershop' => $barbershop,
            'barber' => $barber->load(['user', 'services']),
            'services' => $barbershop->services()->orderBy('name')->get(),
            'roleLabel' => $manager ? 'Administración' : 'Barbero',
            'canManageMembers' => $manager,
            'manager' => $manager,
        ]);
    }

    private function save(Request $request, Barbershop $barbershop, Barber $barber, bool $manager): RedirectResponse
    {
        $validated = $request->validate([
            'service_ids' => ['sometimes', 'array'],
            'service_ids.*' => [
                'required',
                'integer',
                Rule::exists('services', 'id')->where('barbershop_id', $barbershop->id),
            ],
        ]);

        $barber->services()->sync($validated['service_ids'] ?? []);

        $route = $manager ? 'tenant.barber-services.edit' : 'tenant.barber-services.self.edit';
        $parameters = $manager ? [$barbershop, $barber] : [$barbershop];

        return redirect()
            ->route($route, $parameters)
            ->with('status', 'Servicios del barbero actualizados correctamente.');
    }

    private function ensureBarberBelongsToShop(Barbershop $barbershop, Barber $barber): void
    {
        abort_unless($barber->barbershop_id === $barbershop->id, 404);
    }

    private function authorizeManager(Request $request, Barbershop $barbershop): void
    {
        $user = $request->user();

        abort_unless($user !== null, 403);

        if ($user->is_platform_admin) {
            return;
        }

        $canManage = $user->memberships()
            ->where('barbershop_id', $barbershop->id)
            ->where('status', Membership::STATUS_ACTIVE)
            ->whereIn('role', [Membership::ROLE_OWNER, Membership::ROLE_ADMIN])
            ->exists();

        abort_unless($canManage, 403);
    }
}
