<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantReportRequest;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\User;
use App\Support\TenantDashboard;
use App\Support\TenantReportData;
use Illuminate\View\View;

final class TenantReportController extends Controller
{
    public function summary(TenantReportRequest $request, Barbershop $barbershop): View
    {
        $data = new TenantReportData($request, $barbershop);
        $counts = $data->query()
            ->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed', [Appointment::STATUS_COMPLETED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS cancelled', [Appointment::STATUS_CANCELLED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS no_show', [Appointment::STATUS_NO_SHOW])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS scheduled', [Appointment::STATUS_SCHEDULED])
            ->first();
        $user = $request->user();
        $membership = $user instanceof User ? TenantDashboard::membershipFor($user, $barbershop) : null;

        return view('tenant.reports.summary', [
            'barbershop' => $barbershop,
            'summary' => [
                'total' => (int) $counts->total,
                'completed' => (int) $counts->completed,
                'cancelled' => (int) $counts->cancelled,
                'no_show' => (int) $counts->no_show,
                'scheduled' => (int) $counts->scheduled,
            ],
            'reportData' => $data,
            'barbers' => $data->barbers(),
            'services' => $data->services(),
            'barberId' => $data->barberId(),
            'serviceId' => $data->serviceId(),
            'roleLabel' => TenantDashboard::roleLabel($membership),
            'canManageMembers' => true,
        ]);
    }
}
