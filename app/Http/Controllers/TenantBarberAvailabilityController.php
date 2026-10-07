<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use App\Models\BarberAvailability;
use App\Models\Barbershop;
use App\Models\BusinessHour;
use App\Models\Membership;
use App\Models\User;
use App\Support\TenantDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class TenantBarberAvailabilityController extends Controller
{
    public function index(Request $request, Barbershop $barbershop): View
    {
        $this->authorizeManagement($barbershop);

        /** @var User $user */
        $user = $request->user();

        $barbers = $barbershop->barbers()
            ->with(['user', 'availabilities' => function ($query): void {
                $query->orderBy('weekday');
            }])
            ->join('users', 'users.id', '=', 'barbers.user_id')
            ->orderBy('barbers.active', 'desc')
            ->orderBy('barbers.display_name')
            ->select('barbers.*')
            ->get();

        $openBusinessHours = $barbershop->businessHours()
            ->where('closed', false)
            ->orderBy('weekday')
            ->get();

        return view('tenant.barber-availability-index', [
            'barbershop' => $barbershop,
            'barbers' => $barbers,
            'activeBarbers' => $barbers->where('active', true)->values(),
            'openBusinessHours' => $openBusinessHours,
            'weekdayLabels' => BusinessHour::weekdayLabels(),
            'roleLabel' => TenantDashboard::roleLabel(TenantDashboard::membershipFor($user, $barbershop)),
            'canManageMembers' => true,
        ]);
    }

    public function store(Request $request, Barbershop $barbershop): RedirectResponse
    {
        $this->authorizeManagement($barbershop);

        $validated = $request->validate($this->rules($barbershop));
        $barber = $this->validatedActiveBarber($barbershop, (int) $validated['barber_id']);
        $this->validateBusinessHourBounds($barbershop, (int) $validated['weekday'], $validated['starts_at'], $validated['ends_at']);

        $barber->availabilities()->create([
            'weekday' => $validated['weekday'],
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'active' => (bool) ($validated['active'] ?? true),
        ]);

        return redirect()
            ->route('tenant.barber-availability.index', $barbershop)
            ->with('status', 'Disponibilidad creada correctamente.');
    }

    public function update(Request $request, Barbershop $barbershop, BarberAvailability $barberAvailability): RedirectResponse
    {
        $barberAvailability->load('barber');

        abort_unless($barberAvailability->barber->barbershop_id === $barbershop->id, 404);

        $this->authorizeManagement($barbershop);

        $validated = $request->validate($this->rules($barbershop, $barberAvailability));
        $this->validateBusinessHourBounds($barbershop, (int) $validated['weekday'], $validated['starts_at'], $validated['ends_at']);

        $barberAvailability->update([
            'weekday' => $validated['weekday'],
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'active' => (bool) $validated['active'],
        ]);

        return redirect()
            ->route('tenant.barber-availability.index', $barbershop)
            ->with('status', 'Disponibilidad actualizada correctamente.');
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
    private function rules(Barbershop $barbershop, ?BarberAvailability $barberAvailability = null): array
    {
        $barberId = $barberAvailability?->barber_id ?? request()->integer('barber_id');

        return [
            'barber_id' => $barberAvailability === null
                ? ['required', 'integer', Rule::exists(Barber::class, 'id')]
                : ['missing'],
            'weekday' => [
                'required',
                'integer',
                'min:'.BusinessHour::WEEKDAY_SUNDAY,
                'max:'.BusinessHour::WEEKDAY_SATURDAY,
                Rule::unique(BarberAvailability::class, 'weekday')
                    ->where(fn ($query) => $query->where('barber_id', $barberId))
                    ->ignore($barberAvailability),
            ],
            'starts_at' => ['required', 'date_format:H:i', 'before:ends_at'],
            'ends_at' => ['required', 'date_format:H:i'],
            'active' => [$barberAvailability === null ? 'sometimes' : 'required', 'boolean'],
        ];
    }

    private function validatedActiveBarber(Barbershop $barbershop, int $barberId): Barber
    {
        $barber = Barber::query()
            ->whereKey($barberId)
            ->where('barbershop_id', $barbershop->id)
            ->where('active', true)
            ->first();

        if ($barber === null) {
            throw ValidationException::withMessages([
                'barber_id' => 'Selecciona un barbero activo de esta barbería.',
            ]);
        }

        return $barber;
    }

    private function validateBusinessHourBounds(Barbershop $barbershop, int $weekday, string $startsAt, string $endsAt): void
    {
        $businessHour = $barbershop->businessHours()
            ->where('weekday', $weekday)
            ->where('closed', false)
            ->first();

        if ($businessHour === null || $businessHour->opens_at === null || $businessHour->closes_at === null) {
            throw ValidationException::withMessages([
                'weekday' => 'El día seleccionado debe tener un horario de negocio abierto.',
            ]);
        }

        $opensAt = mb_substr((string) $businessHour->opens_at, 0, 5);
        $closesAt = mb_substr((string) $businessHour->closes_at, 0, 5);

        if ($startsAt < $opensAt || $endsAt > $closesAt) {
            throw ValidationException::withMessages([
                'starts_at' => 'La disponibilidad debe estar dentro del horario de negocio del día seleccionado.',
            ]);
        }
    }
}
