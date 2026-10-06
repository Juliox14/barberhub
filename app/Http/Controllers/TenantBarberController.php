<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use App\Support\TenantDashboard;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class TenantBarberController extends Controller
{
    public function index(Request $request, Barbershop $barbershop): View
    {
        $this->authorizeManagement($barbershop);

        /** @var User $user */
        $user = $request->user();

        $barbers = $barbershop->barbers()
            ->with('user')
            ->join('users', 'users.id', '=', 'barbers.user_id')
            ->orderBy('barbers.active', 'desc')
            ->orderBy('barbers.display_name')
            ->select('barbers.*')
            ->get();

        $eligibleMemberships = $barbershop->memberships()
            ->with('user')
            ->where('memberships.status', Membership::STATUS_ACTIVE)
            ->whereIn('memberships.role', $this->schedulableRoles())
            ->whereDoesntHave('user.barberProfiles', function ($query) use ($barbershop): void {
                $query->where('barbers.barbershop_id', $barbershop->id);
            })
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->orderBy('users.name')
            ->select('memberships.*')
            ->get();

        return view('tenant.barbers-index', [
            'barbershop' => $barbershop,
            'barbers' => $barbers,
            'eligibleMemberships' => $eligibleMemberships,
            'roleLabel' => TenantDashboard::roleLabel(TenantDashboard::membershipFor($user, $barbershop)),
            'canManageMembers' => true,
        ]);
    }

    public function store(Request $request, Barbershop $barbershop): RedirectResponse
    {
        $this->authorizeManagement($barbershop);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists(User::class, 'id')],
            'display_name' => ['required', 'string', 'max:255'],
        ]);

        $this->validateSchedulableMembership($barbershop, (int) $validated['user_id']);
        $this->validateUniqueProfile($barbershop, (int) $validated['user_id']);

        try {
            DB::transaction(function () use ($barbershop, $validated): void {
                Barber::query()->create([
                    'barbershop_id' => $barbershop->id,
                    'user_id' => (int) $validated['user_id'],
                    'display_name' => $validated['display_name'],
                    'active' => true,
                ]);
            });
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'user_id' => 'Esta persona ya tiene un perfil de barbero en la barbería.',
            ])->errorBag('default');
        }

        return redirect()
            ->route('tenant.barbers.index', $barbershop)
            ->with('status', 'Perfil de barbero creado correctamente.');
    }

    public function update(Request $request, Barbershop $barbershop, Barber $barber): RedirectResponse
    {
        abort_unless($barber->barbershop_id === $barbershop->id, 404);

        $this->authorizeManagement($barbershop);

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
        ]);

        $barber->update([
            'display_name' => $validated['display_name'],
            'active' => (bool) $validated['active'],
        ]);

        return redirect()
            ->route('tenant.barbers.index', $barbershop)
            ->with('status', 'Perfil de barbero actualizado correctamente.');
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

    private function validateSchedulableMembership(Barbershop $barbershop, int $userId): void
    {
        $hasMembership = Membership::query()
            ->where('barbershop_id', $barbershop->id)
            ->where('user_id', $userId)
            ->where('status', Membership::STATUS_ACTIVE)
            ->whereIn('role', $this->schedulableRoles())
            ->exists();

        if (! $hasMembership) {
            throw ValidationException::withMessages([
                'user_id' => 'Selecciona un miembro activo de esta barbería con rol operativo.',
            ]);
        }
    }

    private function validateUniqueProfile(Barbershop $barbershop, int $userId): void
    {
        $exists = Barber::query()
            ->where('barbershop_id', $barbershop->id)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'user_id' => 'Esta persona ya tiene un perfil de barbero en la barbería.',
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function schedulableRoles(): array
    {
        return [
            Membership::ROLE_OWNER,
            Membership::ROLE_ADMIN,
            Membership::ROLE_BARBER,
        ];
    }
}
