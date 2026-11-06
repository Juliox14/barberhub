<?php

namespace App\Http\Controllers;

use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\User;
use App\Support\TableSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PlatformMemberController extends Controller
{
    public function index(Request $request): View
    {
        $selectedBarbershop = $request->filled('barbershop')
            ? Barbershop::query()->where('slug', $request->query('barbershop'))->firstOrFail()
            : null;

        $teamColumns = [
            'name' => static fn (Builder $query, string $direction): mixed => $query->orderBy(
                User::query()->select('name')->whereColumn('users.id', 'memberships.user_id'), $direction,
            ),
            'email' => static fn (Builder $query, string $direction): mixed => $query->orderBy(
                User::query()->select('email')->whereColumn('users.id', 'memberships.user_id'), $direction,
            ),
            'barbershop' => static fn (Builder $query, string $direction): mixed => $query->orderBy(
                Barbershop::query()->select('name')->whereColumn('barbershops.id', 'memberships.barbershop_id'), $direction,
            ),
            'role' => static function (Builder $query, string $direction): void {
                $query->orderByRaw("CASE memberships.role WHEN 'admin' THEN 'Administrador' WHEN 'barber' THEN 'Barbero' WHEN 'owner' THEN 'Dueño' ELSE memberships.role END {$direction}");
            },
            'status' => static function (Builder $query, string $direction): void {
                $query->orderByRaw("CASE memberships.status WHEN 'active' THEN 'Activo' ELSE 'Inactivo' END {$direction}");
            },
        ];
        $teamSort = TableSort::state($request, $teamColumns, '', 'asc', 'team_sort', 'team_direction');
        $teamQuery = Membership::query()
            ->with(['user', 'barbershop'])
            ->when($selectedBarbershop, fn ($query) => $query->where('barbershop_id', $selectedBarbershop->id))
            ->orderBy('barbershop_id')->orderBy('role')->orderBy('id');
        if ($teamSort['requested']) {
            TableSort::apply($teamQuery, $teamColumns, $teamSort['sort'], $teamSort['direction']);
        }
        $memberships = $teamQuery->paginate(15, ['*'], 'team_page')->withQueryString();

        $customerColumns = [
            'name' => 'customers.name',
            'email' => static function (Builder $query, string $direction): void {
                $query->orderByRaw("COALESCE(customers.email, 'Sin correo') {$direction}");
            },
            'phone' => static function (Builder $query, string $direction): void {
                $query->orderByRaw("COALESCE(customers.phone, 'Sin teléfono') {$direction}");
            },
            'barbershop' => static fn (Builder $query, string $direction): mixed => $query->orderBy(
                Barbershop::query()->select('name')->whereColumn('barbershops.id', 'customers.barbershop_id'), $direction,
            ),
            'account' => static function (Builder $query, string $direction): void {
                $query->orderByRaw("CASE WHEN customers.user_id IS NULL THEN 'Sin cuenta' ELSE 'Vinculada' END {$direction}");
            },
        ];
        $customerSort = TableSort::state($request, $customerColumns, 'name', 'asc', 'customers_sort', 'customers_direction');
        $customerQuery = Customer::query()
            ->with('barbershop')
            ->when($selectedBarbershop, fn ($query) => $query->where('barbershop_id', $selectedBarbershop->id))
            ->orderBy('name')->orderBy('id');
        if ($customerSort['requested']) {
            TableSort::apply($customerQuery, $customerColumns, $customerSort['sort'], $customerSort['direction']);
        }
        $customers = $customerQuery->paginate(15, ['*'], 'customers_page')->withQueryString();

        return view('platform.members-index', [
            'barbershops' => Barbershop::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'selectedBarbershop' => $selectedBarbershop,
            'memberships' => $memberships,
            'customers' => $customers,
            'teamSort' => $teamSort,
            'customerSort' => $customerSort,
            'roleLabels' => [
                Membership::ROLE_OWNER => 'Dueño',
                Membership::ROLE_ADMIN => 'Administrador',
                Membership::ROLE_BARBER => 'Barbero',
            ],
        ]);
    }
}
