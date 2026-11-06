<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantReportRequest;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Service;
use App\Models\User;
use App\Support\TenantDashboard;
use App\Support\TenantReportData;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class TenantBusinessReportController extends Controller
{
    public function services(TenantReportRequest $request, Barbershop $barbershop): View
    {
        $data = new TenantReportData($request, $barbershop);
        $serviceCounts = $data->query()
            ->join('services', function (JoinClause $join) use ($barbershop): void {
                $join->on('services.id', '=', 'appointments.service_id')
                    ->where('services.barbershop_id', '=', $barbershop->id);
            })
            ->where('appointments.status', Appointment::STATUS_COMPLETED)
            ->selectRaw('services.id, COUNT(appointments.id) AS completed')
            ->groupBy('services.id')
            ->get()
            ->keyBy('id');
        $completedTotal = (int) $serviceCounts->sum('completed');
        $serviceOptions = $data->services();
        $services = ($data->serviceId() === null
            ? $serviceOptions
            : $serviceOptions->where('id', $data->serviceId())->values())
            ->map(fn (Service $service): array => [
                'name' => $service->name,
                'completed' => (int) ($serviceCounts->get($service->id)?->completed ?? 0),
            ])
            ->sortBy(fn (array $service): array => [-$service['completed'], mb_strtolower($service['name'])])
            ->values()
            ->map(fn (array $service): array => [
                ...$service,
                'share' => $completedTotal === 0 ? 0.0 : round($service['completed'] * 100 / $completedTotal, 1),
            ])
            ->all();

        return view('tenant.reports.services', [
            ...$this->sharedViewData($request, $barbershop, $data),
            'serviceRows' => $services,
            'completedTotal' => $completedTotal,
        ]);
    }

    public function barbers(TenantReportRequest $request, Barbershop $barbershop): View
    {
        $data = new TenantReportData($request, $barbershop);
        $counts = $data->query()
            ->join('barbers', function (JoinClause $join) use ($barbershop): void {
                $join->on('barbers.id', '=', 'appointments.barber_id')
                    ->where('barbers.barbershop_id', '=', $barbershop->id);
            })
            ->selectRaw('barbers.id')
            ->selectRaw('SUM(CASE WHEN appointments.status = ? THEN 1 ELSE 0 END) AS completed', [Appointment::STATUS_COMPLETED])
            ->selectRaw('SUM(CASE WHEN appointments.status = ? THEN 1 ELSE 0 END) AS cancelled', [Appointment::STATUS_CANCELLED])
            ->selectRaw('SUM(CASE WHEN appointments.status = ? THEN 1 ELSE 0 END) AS no_show', [Appointment::STATUS_NO_SHOW])
            ->selectRaw('SUM(CASE WHEN appointments.status = ? THEN 1 ELSE 0 END) AS scheduled', [Appointment::STATUS_SCHEDULED])
            ->groupBy('barbers.id')
            ->get()
            ->keyBy('id');
        $bookedMinutes = [];
        $wallClockZone = new DateTimeZone('UTC');
        $data->query()
            ->join('barbers', function (JoinClause $join) use ($barbershop): void {
                $join->on('barbers.id', '=', 'appointments.barber_id')
                    ->where('barbers.barbershop_id', '=', $barbershop->id);
            })
            ->where('appointments.status', Appointment::STATUS_COMPLETED)
            ->select(['appointments.id', 'appointments.barber_id', 'appointments.starts_at', 'appointments.ends_at'])
            ->orderBy('appointments.id')
            ->lazyById(500, 'appointments.id', 'id')
            ->each(function (object $appointment) use (&$bookedMinutes, $wallClockZone): void {
                $start = new DateTimeImmutable((string) $appointment->starts_at, $wallClockZone);
                $end = new DateTimeImmutable((string) $appointment->ends_at, $wallClockZone);
                $bookedMinutes[$appointment->barber_id] = ($bookedMinutes[$appointment->barber_id] ?? 0)
                    + (($end->getTimestamp() - $start->getTimestamp()) / 60);
            });
        $barberOptions = $data->barbers();
        $barbers = ($data->barberId() === null
            ? $barberOptions
            : $barberOptions->where('id', $data->barberId())->values())
            ->map(fn (Barber $barber): array => [
                'name' => $barber->display_name,
                'completed' => (int) ($counts->get($barber->id)?->completed ?? 0),
                'cancelled' => (int) ($counts->get($barber->id)?->cancelled ?? 0),
                'noShow' => (int) ($counts->get($barber->id)?->no_show ?? 0),
                'scheduled' => (int) ($counts->get($barber->id)?->scheduled ?? 0),
                'bookedMinutes' => round($bookedMinutes[$barber->id] ?? 0, 1),
            ])
            ->sortBy(fn (array $barber): array => [-$barber['completed'], mb_strtolower($barber['name'])])
            ->values()
            ->all();
        $totals = [
            'completed' => array_sum(array_column($barbers, 'completed')),
            'cancelled' => array_sum(array_column($barbers, 'cancelled')),
            'noShow' => array_sum(array_column($barbers, 'noShow')),
            'scheduled' => array_sum(array_column($barbers, 'scheduled')),
            'bookedMinutes' => round(array_sum(array_column($barbers, 'bookedMinutes')), 1),
        ];

        return view('tenant.reports.barbers', [
            ...$this->sharedViewData($request, $barbershop, $data),
            'barberRows' => $barbers,
            'totals' => $totals,
        ]);
    }

    public function customers(TenantReportRequest $request, Barbershop $barbershop): View
    {
        $data = new TenantReportData($request, $barbershop);
        $firstVisits = Appointment::query()
            ->selectRaw('customer_id, MIN(starts_at) AS first_visit')
            ->where('barbershop_id', $barbershop->id)
            ->where('status', Appointment::STATUS_COMPLETED)
            ->whereNotNull('customer_id')
            ->groupBy('customer_id');
        $attendedCustomers = $data->query()
            ->join('customers', function (JoinClause $join) use ($barbershop): void {
                $join->on('customers.id', '=', 'appointments.customer_id')
                    ->where('customers.barbershop_id', '=', $barbershop->id);
            })
            ->joinSub($firstVisits, 'customer_first_visits', function (JoinClause $join): void {
                $join->on('customer_first_visits.customer_id', '=', 'customers.id');
            })
            ->where('appointments.status', Appointment::STATUS_COMPLETED)
            ->selectRaw('customers.id AS customer_id, customer_first_visits.first_visit')
            ->groupBy('customers.id', 'customer_first_visits.first_visit');
        $customerCounts = DB::query()
            ->fromSub($attendedCustomers, 'attended_customers')
            ->selectRaw('COUNT(*) AS unique_customers')
            ->selectRaw('SUM(CASE WHEN first_visit < ? THEN 1 ELSE 0 END) AS returning_customers', [$data->from()->format('Y-m-d 00:00:00')])
            ->first();
        $unidentifiedAppointments = $data->query()
            ->where('status', Appointment::STATUS_COMPLETED)
            ->whereNull('customer_id')
            ->count();
        $customerSummary = [
            'unique' => (int) $customerCounts->unique_customers,
            'returning' => (int) $customerCounts->returning_customers,
            'new' => (int) $customerCounts->unique_customers - (int) $customerCounts->returning_customers,
            'unidentifiedAppointments' => $unidentifiedAppointments,
        ];

        return view('tenant.reports.customers', [
            ...$this->sharedViewData($request, $barbershop, $data),
            'customerSummary' => $customerSummary,
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
            'barberOptions' => $data->barbers(),
            'serviceOptions' => $data->services(),
            'barberId' => $data->barberId(),
            'serviceId' => $data->serviceId(),
            'roleLabel' => TenantDashboard::roleLabel($membership),
        ];
    }
}
