<?php

namespace App\Http\Controllers;

use App\Support\TenantDashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TenantSelectorController extends Controller
{
    public function __invoke(Request $request): View
    {
        $memberships = TenantDashboard::activeMembershipsFor($request->user());

        abort_if($memberships->isEmpty(), 403, 'Todavía no tienes una barbería activa asignada.');

        return view('dashboards.selector', [
            'memberships' => $memberships,
        ]);
    }
}
