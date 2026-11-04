<?php

namespace App\Http\Controllers;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
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

        abort_unless(
            $user->is_platform_admin || in_array($membership?->role, [Membership::ROLE_OWNER, Membership::ROLE_ADMIN], true),
            403,
        );

        $customers = $barbershop->customers()
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15, ['*'], 'customers_page')
            ->withQueryString();

        return view('tenant.customers-index', [
            'barbershop' => $barbershop,
            'customers' => $customers,
            'roleLabel' => TenantDashboard::roleLabel($membership),
            'canManageMembers' => true,
        ]);
    }
}
