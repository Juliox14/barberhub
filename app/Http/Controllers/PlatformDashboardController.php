<?php

namespace App\Http\Controllers;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use App\Support\CountryNames;
use App\Support\TableSort;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PlatformDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $sortColumns = [
            'name' => 'name',
            'status' => 'status',
            'timezone' => 'timezone',
            'country' => 'country_code',
            'region' => 'region',
            'city' => 'city',
            'members' => 'memberships_count',
            'created_at' => 'created_at',
        ];
        $sortState = TableSort::state($request, $sortColumns, 'created_at', 'desc');
        $barbershops = Barbershop::query()
            ->withCount('memberships')
            ->latest()
            ->take(6)
            ->get();

        if ($sortState['requested']) {
            $sortColumn = $sortColumns[$sortState['sort']];
            $barbershops = $barbershops
                ->sortBy(
                    fn (Barbershop $barbershop): mixed => match ($sortState['sort']) {
                        'country' => CountryNames::name($barbershop->country_code) ?? '—',
                        'region' => $barbershop->region ?: '—',
                        'city' => $barbershop->city ?: '—',
                        default => $barbershop->{$sortColumn},
                    },
                    in_array($sortState['sort'], ['name', 'status', 'timezone', 'country', 'region', 'city'], true)
                        ? (SORT_NATURAL | SORT_FLAG_CASE)
                        : SORT_REGULAR,
                    $sortState['direction'] === 'desc',
                )
                ->values();
        }

        return view('dashboards.platform', [
            'activeBarbershops' => Barbershop::query()
                ->where('status', Barbershop::STATUS_ACTIVE)
                ->count(),
            'inactiveBarbershops' => Barbershop::query()
                ->where('status', Barbershop::STATUS_INACTIVE)
                ->count(),
            'registeredUsers' => User::query()->count(),
            'activeMemberships' => Membership::query()
                ->where('status', Membership::STATUS_ACTIVE)
                ->count(),
            'barbershops' => $barbershops,
            'sortState' => $sortState,
        ]);
    }
}
