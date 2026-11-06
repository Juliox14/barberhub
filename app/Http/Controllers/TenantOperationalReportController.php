<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantReportRequest;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\User;
use App\Support\TenantDashboard;
use App\Support\TenantReportData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\View\View;

final class TenantOperationalReportController extends Controller
{
    public function evolution(TenantReportRequest $request, Barbershop $barbershop): View
    {
        $data = new TenantReportData($request, $barbershop);
        $grouping = in_array($request->query('grouping'), ['week', 'month'], true)
            ? $request->query('grouping')
            : 'day';
        $evolution = [];
        $cursor = $data->from();

        while ($cursor->lessThanOrEqualTo($data->to())) {
            $key = $this->bucketKey($cursor, $grouping);
            $evolution[$key] ??= ['label' => $this->bucketLabel($cursor, $grouping), 'count' => 0];
            $cursor = match ($grouping) {
                'week' => $cursor->startOfWeek()->addWeek(),
                'month' => $cursor->startOfMonth()->addMonth(),
                default => $cursor->addDay(),
            };
        }

        foreach ($this->appointmentsForPeriod($data, $data->from(), $data->endExclusive())
            ->where('status', Appointment::STATUS_COMPLETED)
            ->toBase()
            ->select('starts_at')
            ->cursor() as $appointment) {
            $date = CarbonImmutable::parse($appointment->starts_at, $data->timezone());
            $evolution[$this->bucketKey($date, $grouping)]['count']++;
        }

        $previousFrom = $data->from()->subDays($data->periodDays());
        $previousCount = $this->appointmentsForPeriod($data, $previousFrom, $data->from())
            ->where('status', Appointment::STATUS_COMPLETED)
            ->count();
        $currentCount = array_sum(array_column($evolution, 'count'));

        return view('tenant.reports.evolution', [
            ...$this->sharedViewData($request, $barbershop, $data),
            'evolution' => array_values($evolution),
            'grouping' => $grouping,
            'comparison' => [
                'previousFrom' => $previousFrom->toDateString(),
                'previousTo' => $data->from()->subDay()->toDateString(),
                'previousTotal' => $previousCount,
                'currentTotal' => $currentCount,
                'difference' => $currentCount - $previousCount,
                'percentageDifference' => $previousCount === 0
                    ? null
                    : round((($currentCount - $previousCount) / $previousCount) * 100, 1),
            ],
        ]);
    }

    public function demand(TenantReportRequest $request, Barbershop $barbershop): View
    {
        $data = new TenantReportData($request, $barbershop);
        $weekdayCounts = array_fill(1, 7, 0);
        $hourCounts = array_fill(0, 24, 0);
        $total = 0;

        foreach ($data->query()->toBase()->select('starts_at')->cursor() as $appointment) {
            $startsAt = CarbonImmutable::parse($appointment->starts_at, $data->timezone());
            $weekdayCounts[$startsAt->dayOfWeekIso]++;
            $hourCounts[$startsAt->hour]++;
            $total++;
        }

        return view('tenant.reports.demand', [
            ...$this->sharedViewData($request, $barbershop, $data),
            'weekdayCounts' => $weekdayCounts,
            'hourCounts' => $hourCounts,
            'demandTotal' => $total,
            'weekdayLabels' => [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'],
        ]);
    }

    /** @return array<string, mixed> */
    private function sharedViewData(TenantReportRequest $request, Barbershop $barbershop, TenantReportData $data): array
    {
        $user = $request->user();
        $membership = $user instanceof User ? TenantDashboard::membershipFor($user, $barbershop) : null;

        return [
            'barbershop' => $barbershop,
            'reportData' => $data,
            'barbers' => $data->barbers(),
            'services' => $data->services(),
            'barberId' => $data->barberId(),
            'serviceId' => $data->serviceId(),
            'roleLabel' => TenantDashboard::roleLabel($membership),
        ];
    }

    /** @return HasMany<Appointment, Barbershop> */
    private function appointmentsForPeriod(TenantReportData $data, CarbonImmutable $from, CarbonImmutable $endExclusive): HasMany
    {
        return $data->barbershop->appointments()
            ->where('starts_at', '>=', $from->startOfDay())
            ->where('starts_at', '<', $endExclusive->startOfDay())
            ->when($data->barberId() !== null, fn (HasMany $query): HasMany => $query->where('barber_id', $data->barberId()))
            ->when($data->serviceId() !== null, fn (HasMany $query): HasMany => $query->where('service_id', $data->serviceId()));
    }

    private function bucketKey(CarbonImmutable $date, string $grouping): string
    {
        return match ($grouping) {
            'week' => $date->startOfWeek()->toDateString(),
            'month' => $date->format('Y-m'),
            default => $date->toDateString(),
        };
    }

    private function bucketLabel(CarbonImmutable $date, string $grouping): string
    {
        return match ($grouping) {
            'week' => $date->startOfWeek()->format('d/m/Y'),
            'month' => $date->format('m/Y'),
            default => $date->toDateString(),
        };
    }
}
