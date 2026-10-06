<?php

namespace App\Http\Controllers;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use App\Support\TenantDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class TenantServiceController extends Controller
{
    public function index(Request $request, Barbershop $barbershop): View
    {
        $this->authorizeManagement($barbershop);

        /** @var User $user */
        $user = $request->user();

        $services = $barbershop->services()
            ->orderBy('active', 'desc')
            ->orderBy('name')
            ->get();

        return view('tenant.services-index', [
            'barbershop' => $barbershop,
            'services' => $services,
            'roleLabel' => TenantDashboard::roleLabel(TenantDashboard::membershipFor($user, $barbershop)),
            'canManageMembers' => true,
        ]);
    }

    public function store(Request $request, Barbershop $barbershop): RedirectResponse
    {
        $this->authorizeManagement($barbershop);

        $validated = $request->validate($this->rules($barbershop));

        $barbershop->services()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'duration_minutes' => $validated['duration_minutes'],
            'active' => $validated['active'] ?? true,
        ]);

        return redirect()
            ->route('tenant.services.index', $barbershop)
            ->with('status', 'Servicio creado correctamente.');
    }

    public function update(Request $request, Barbershop $barbershop, Service $service): RedirectResponse
    {
        abort_unless($service->barbershop_id === $barbershop->id, 404);

        $this->authorizeManagement($barbershop);

        $validated = $request->validate($this->rules($barbershop, $service));

        $service->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'duration_minutes' => $validated['duration_minutes'],
            'active' => (bool) $validated['active'],
        ]);

        return redirect()
            ->route('tenant.services.index', $barbershop)
            ->with('status', 'Servicio actualizado correctamente.');
    }

    private function authorizeManagement(Barbershop $barbershop): void
    {
        $user = request()->user();

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

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(Barbershop $barbershop, ?Service $service = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Service::class, 'name')
                    ->where(fn ($query) => $query->where('barbershop_id', $barbershop->id))
                    ->ignore($service),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'active' => [$service === null ? 'sometimes' : 'required', 'boolean'],
        ];
    }
}
