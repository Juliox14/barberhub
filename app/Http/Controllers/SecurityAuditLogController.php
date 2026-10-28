<?php

namespace App\Http\Controllers;

use App\Models\SecurityAuditLog;
use Illuminate\View\View;

final class SecurityAuditLogController extends Controller
{
    public function index(): View
    {
        $auditLogs = SecurityAuditLog::query()
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate(25);

        return view('platform.security-audit-logs', [
            'auditLogs' => $auditLogs,
        ]);
    }
}
