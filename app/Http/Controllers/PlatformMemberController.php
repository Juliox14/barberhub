<?php

namespace App\Http\Controllers;

use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PlatformMemberController extends Controller
{
    public function index(Request $request): View
    {
        $selectedBarbershop = $request->filled('barbershop')
            ? Barbershop::query()->where('slug', $request->query('barbershop'))->firstOrFail()
            : null;

        $memberships = Membership::query()
            ->with(['user', 'barbershop'])
            ->when($selectedBarbershop, fn ($query) => $query->where('barbershop_id', $selectedBarbershop->id))
            ->orderBy('barbershop_id')->orderBy('role')->orderBy('id')
            ->paginate(15, ['*'], 'team_page')
            ->withQueryString();

        $customers = Customer::query()
            ->with('barbershop')
            ->when($selectedBarbershop, fn ($query) => $query->where('barbershop_id', $selectedBarbershop->id))
            ->orderBy('name')->orderBy('id')
            ->paginate(15, ['*'], 'customers_page')
            ->withQueryString();

        return view('platform.members-index', [
            'barbershops' => Barbershop::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'selectedBarbershop' => $selectedBarbershop,
            'memberships' => $memberships,
            'customers' => $customers,
            'roleLabels' => [
                Membership::ROLE_OWNER => 'Dueño',
                Membership::ROLE_ADMIN => 'Administrador',
                Membership::ROLE_BARBER => 'Barbero',
            ],
        ]);
    }
}
