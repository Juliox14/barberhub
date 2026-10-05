<?php

namespace App\Http\Controllers;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use App\Support\TenantDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class TenantMembershipController extends Controller
{
    public function index(Request $request, Barbershop $barbershop): View
    {
        $this->authorizeManagement($barbershop);

        /** @var User $user */
        $user = $request->user();

        $memberships = $barbershop->memberships()
            ->with('user')
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->orderBy('users.name')
            ->select('memberships.*')
            ->get();

        return view('tenant.members-index', [
            'barbershop' => $barbershop,
            'memberships' => $memberships,
            'roleLabels' => $this->roleLabels(),
            'statusLabels' => $this->statusLabels(),
            'roleLabel' => TenantDashboard::roleLabel(TenantDashboard::membershipFor($user, $barbershop)),
            'canManageMembers' => true,
        ]);
    }

    public function store(Request $request, Barbershop $barbershop): RedirectResponse
    {
        $this->authorizeManagement($barbershop);

        $validated = $request->validate([
            'email' => ['required', 'email', Rule::exists(User::class, 'email')],
            'role' => ['required', Rule::in($this->roles())],
            'status' => ['nullable', Rule::in($this->statuses())],
        ], [
            'email.exists' => 'El correo debe pertenecer a una cuenta existente.',
        ]);

        DB::transaction(function () use ($barbershop, $validated): void {
            $user = User::query()
                ->where('email', $validated['email'])
                ->firstOrFail();

            $membership = Membership::query()->firstOrNew([
                'user_id' => $user->id,
                'barbershop_id' => $barbershop->id,
            ]);

            $status = $validated['status'] ?? Membership::STATUS_ACTIVE;

            $this->ensureLastActiveOwnerRemains($barbershop, $membership, $validated['role'], $status);

            $membership->fill([
                'role' => $validated['role'],
                'status' => $status,
            ])->save();
        });

        return redirect()
            ->route('tenant.members.index', $barbershop)
            ->with('status', 'Membresía guardada correctamente.');
    }

    public function update(Request $request, Barbershop $barbershop, Membership $membership): RedirectResponse
    {
        abort_unless($membership->barbershop_id === $barbershop->id, 404);

        $this->authorizeManagement($barbershop);

        $validated = $request->validate([
            'role' => ['required', Rule::in($this->roles())],
            'status' => ['required', Rule::in($this->statuses())],
        ]);

        DB::transaction(function () use ($barbershop, $membership, $validated): void {
            $membership->refresh();

            $this->ensureLastActiveOwnerRemains($barbershop, $membership, $validated['role'], $validated['status']);

            $membership->update([
                'role' => $validated['role'],
                'status' => $validated['status'],
            ]);
        });

        return redirect()
            ->route('tenant.members.index', $barbershop)
            ->with('status', 'Membresía actualizada correctamente.');
    }

    public function destroy(Barbershop $barbershop, Membership $membership): RedirectResponse
    {
        abort_unless($membership->barbershop_id === $barbershop->id, 404);

        $this->authorizeManagement($barbershop);

        DB::transaction(function () use ($barbershop, $membership): void {
            $membership->refresh();

            $this->ensureLastActiveOwnerRemains($barbershop, $membership, Membership::ROLE_BARBER, Membership::STATUS_INACTIVE);

            $membership->delete();
        });

        return redirect()
            ->route('tenant.members.index', $barbershop)
            ->with('status', 'Membresía eliminada correctamente.');
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

    private function ensureLastActiveOwnerRemains(Barbershop $barbershop, Membership $membership, string $newRole, string $newStatus): void
    {
        if (! $this->removesActiveOwner($membership, $newRole, $newStatus)) {
            return;
        }

        $activeOwners = Membership::query()
            ->where('barbershop_id', $barbershop->id)
            ->where('role', Membership::ROLE_OWNER)
            ->where('status', Membership::STATUS_ACTIVE)
            ->count();

        if ($activeOwners <= 1) {
            throw ValidationException::withMessages([
                'owner' => 'Debe existir al menos un propietario activo en la barbería.',
            ]);
        }
    }

    private function removesActiveOwner(Membership $membership, string $newRole, string $newStatus): bool
    {
        return $membership->exists
            && $membership->role === Membership::ROLE_OWNER
            && $membership->status === Membership::STATUS_ACTIVE
            && ($newRole !== Membership::ROLE_OWNER || $newStatus !== Membership::STATUS_ACTIVE);
    }

    /**
     * @return array<int, string>
     */
    private function roles(): array
    {
        return [
            Membership::ROLE_OWNER,
            Membership::ROLE_ADMIN,
            Membership::ROLE_BARBER,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function statuses(): array
    {
        return [
            Membership::STATUS_ACTIVE,
            Membership::STATUS_INACTIVE,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function roleLabels(): array
    {
        return [
            Membership::ROLE_OWNER => 'Propietario',
            Membership::ROLE_ADMIN => 'Administrador',
            Membership::ROLE_BARBER => 'Barbero',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function statusLabels(): array
    {
        return [
            Membership::STATUS_ACTIVE => 'Activa',
            Membership::STATUS_INACTIVE => 'Inactiva',
        ];
    }
}
