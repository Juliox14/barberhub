<?php

namespace App\Http\Controllers;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use App\Support\TableSort;
use App\Support\TenantDashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TenantCustomerController extends Controller
{
    public function index(Request $request, Barbershop $barbershop): View
    {
        /** @var User $user */
        $user = $request->user();
        $membership = TenantDashboard::membershipFor($user, $barbershop);

        if (! $user->is_platform_admin && $membership?->role === Membership::ROLE_BARBER) {
            return view('tenant.barber-customers-index', [
                'barbershop' => $barbershop,
                'customers' => collect(),
                'roleLabel' => TenantDashboard::roleLabel($membership),
                'canManageMembers' => false,
            ]);
        }

        abort_unless(
            $user->is_platform_admin || in_array($membership?->role, [Membership::ROLE_OWNER, Membership::ROLE_ADMIN], true),
            403,
        );

        $sortColumns = [
            'name' => 'customers.name',
            'email' => 'customers.email',
            'phone' => 'customers.phone',
        ];
        $sortState = TableSort::state($request, $sortColumns, 'name');
        $customerQuery = $barbershop->customers()->getQuery();
        TableSort::apply($customerQuery, $sortColumns, $sortState['sort'], $sortState['direction']);
        $customers = $customerQuery->paginate(15, ['*'], 'customers_page')->withQueryString();

        return view('tenant.customers-index', [
            'barbershop' => $barbershop,
            'customers' => $customers,
            'sortState' => $sortState,
            'roleLabel' => TenantDashboard::roleLabel($membership),
            'canManageMembers' => true,
        ]);
    }
}
