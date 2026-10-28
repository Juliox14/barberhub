<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class SecurityAuditLogController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $auditLogs = SecurityAuditLog::query()
            ->with([
                'actor:id,name,email',
                'actor.memberships' => fn ($query) => $query->where('status', Membership::STATUS_ACTIVE)->select('id', 'user_id', 'barbershop_id'),
                'actor.memberships.barbershop:id,name',
                'actor.customers:id,user_id,barbershop_id',
                'actor.customers.barbershop:id,name',
                'eventBarbershop:id,name',
                'subjectBarbershop:id,name',
            ])
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate(25);

        $userSubjectIds = $auditLogs->getCollection()
            ->filter(fn (SecurityAuditLog $log): bool => $log->hasUserSubject() && $log->subject_id !== null)
            ->pluck('subject_id')->unique()->values();
        $usersById = $this->loadAccountUsers($userSubjectIds);

        $auditLogs->getCollection()->each(function (SecurityAuditLog $log) use ($usersById): void {
            $log->setRelation('subjectUser', $log->hasUserSubject() ? $usersById->get($log->subject_id) : null);
        });

        $data = ['auditLogs' => $auditLogs];
        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('platform.partials.security-audit-results', $data)->render(),
            ])->header('Cache-Control', 'private, no-store');
        }

        return view('platform.security-audit-logs', $data);
    }

    /**
     * @param  Collection<int, int>  $userIds
     * @return Collection<int, User>
     */
    private function loadAccountUsers(Collection $userIds): Collection
    {
        if ($userIds->isEmpty()) {
            return collect();
        }

        return User::query()->select('id', 'name', 'email')
            ->with([
                'memberships' => fn ($query) => $query->where('status', Membership::STATUS_ACTIVE)->select('id', 'user_id', 'barbershop_id'),
                'memberships.barbershop:id,name',
                'customers:id,user_id,barbershop_id',
                'customers.barbershop:id,name',
            ])->whereKey($userIds)->get()->keyBy('id');
    }
}
