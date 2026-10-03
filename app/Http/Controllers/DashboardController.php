<?php

namespace App\Http\Controllers;

use App\Support\TenantDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $destination = TenantDashboard::destinationFor($request->user());

        abort_if($destination === null, 403, 'Todavía no tienes una barbería activa asignada.');

        return redirect()->route($destination['route'], $destination['parameters']);
    }
}
