<?php

namespace App\Http\Controllers;

use App\Models\Barbershop;
use App\Models\BusinessHour;
use App\Models\Membership;
use App\Models\User;
use App\Support\TableSort;
use App\Support\TenantDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class TenantBusinessHourController extends Controller
{
    public function index(Request $request, Barbershop $barbershop): View
    {
        $this->authorizeManagement($barbershop);

        /** @var User $user */
        $user = $request->user();

        $sortColumns = [
            'weekday' => 'business_hours.weekday',
            'opens_at' => 'business_hours.opens_at',
            'closes_at' => 'business_hours.closes_at',
            'status' => static function ($query, string $direction): void {
                $query->orderByRaw("CASE WHEN business_hours.closed = 1 THEN 'Cerrado' ELSE 'Abierto' END {$direction}");
            },
        ];
        $sortState = TableSort::state($request, $sortColumns, 'weekday');
        $businessHoursQuery = $barbershop->businessHours()->getQuery();
        if ($sortState['requested']) {
            TableSort::apply($businessHoursQuery, $sortColumns, $sortState['sort'], $sortState['direction']);
        } elseif ($request->query->has('sort') || $request->query->has('direction')) {
            $businessHoursQuery->orderBy('weekday')->orderBy('business_hours.id');
        } else {
            $businessHoursQuery->orderByRaw('CASE weekday WHEN 1 THEN 1 WHEN 2 THEN 2 WHEN 3 THEN 3 WHEN 4 THEN 4 WHEN 5 THEN 5 WHEN 6 THEN 6 WHEN 0 THEN 7 END')
                ->orderBy('business_hours.id');
        }
        $businessHours = $businessHoursQuery->get();

        return view('tenant.business-hours-index', [
            'barbershop' => $barbershop,
            'businessHours' => $businessHours,
            'sortState' => $sortState,
            'weekdayLabels' => BusinessHour::weekdayLabels(),
            'roleLabel' => TenantDashboard::roleLabel(TenantDashboard::membershipFor($user, $barbershop)),
            'canManageMembers' => true,
        ]);
    }

    public function store(Request $request, Barbershop $barbershop): RedirectResponse
    {
        $this->authorizeManagement($barbershop);

        $validated = $request->validate($this->rules($barbershop));

        $closed = (bool) ($validated['closed'] ?? false);

        $barbershop->businessHours()->create([
            'weekday' => $validated['weekday'],
            'opens_at' => $closed ? null : $validated['opens_at'],
            'closes_at' => $closed ? null : $validated['closes_at'],
            'closed' => $closed,
        ]);

        return redirect()
            ->route('tenant.business-hours.index', $barbershop)
            ->with('status', 'Horario creado correctamente.');
    }

    public function update(Request $request, Barbershop $barbershop, BusinessHour $businessHour): RedirectResponse
    {
        abort_unless($businessHour->barbershop_id === $barbershop->id, 404);

        $this->authorizeManagement($barbershop);

        $validated = $request->validate($this->rules($barbershop, $businessHour));
        $closed = (bool) $validated['closed'];

        $businessHour->update([
            'opens_at' => $closed ? null : $validated['opens_at'],
            'closes_at' => $closed ? null : $validated['closes_at'],
            'closed' => $closed,
        ]);

        return redirect()
            ->route('tenant.business-hours.index', $barbershop)
            ->with('status', 'Horario actualizado correctamente.');
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
    private function rules(Barbershop $barbershop, ?BusinessHour $businessHour = null): array
    {
        $closed = request()->boolean('closed');

        return [
            'weekday' => $businessHour === null
                ? [
                    'required',
                    'integer',
                    'min:'.BusinessHour::WEEKDAY_SUNDAY,
                    'max:'.BusinessHour::WEEKDAY_SATURDAY,
                    Rule::unique(BusinessHour::class, 'weekday')
                        ->where(fn ($query) => $query->where('barbershop_id', $barbershop->id)),
                ]
                : [
                    'sometimes',
                    'integer',
                    Rule::in([$businessHour->weekday]),
                ],
            'closed' => [$businessHour === null ? 'sometimes' : 'required', 'boolean'],
            'opens_at' => [$closed ? 'nullable' : 'required', 'date_format:H:i', 'before:closes_at'],
            'closes_at' => [$closed ? 'nullable' : 'required', 'date_format:H:i'],
        ];
    }
}
