<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Support\TableSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class SecurityAuditLogController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $sortColumns = [
            'created_at' => 'security_audit_logs.created_at',
            'event' => 'security_audit_logs.event',
            'actor' => static function (Builder $query, string $direction): void {
                $accountName = '(SELECT users.name FROM users WHERE users.id = security_audit_logs.actor_id)';
                $deletedAccount = self::sqlConcat(
                    "'Cuenta eliminada (#'",
                    self::sqlText('security_audit_logs.actor_id'),
                    "')'",
                );
                $query->orderByRaw(
                    "CASE WHEN security_audit_logs.actor_id IS NULL THEN 'Invitado' WHEN {$accountName} IS NOT NULL THEN {$accountName} ELSE {$deletedAccount} END {$direction}",
                );
            },
            'subject' => static function (Builder $query, string $direction): void {
                $userSubjectEvents = [
                    'login.succeeded', 'logout', 'password.updated', 'password.reset',
                    'two_factor.enabled', 'two_factor.disabled', 'two_factor.recovery_regenerated',
                    'two_factor.recovery_used', 'two_factor.failed', 'two_factor.verified',
                    'security.account_locked', 'security.session_revoked',
                    'session.transfer_confirmed', 'tenant.membership.created',
                    'tenant.membership.updated', 'tenant.membership.deleted',
                ];
                $eventPlaceholders = implode(', ', array_fill(0, count($userSubjectEvents), '?'));
                $userName = '(SELECT users.name FROM users WHERE users.id = security_audit_logs.subject_id)';
                $shopName = '(SELECT barbershops.name FROM barbershops WHERE barbershops.id = security_audit_logs.subject_id)';
                $deletedAccount = self::sqlConcat(
                    "'Cuenta eliminada (#'",
                    self::sqlText('security_audit_logs.subject_id'),
                    "')'",
                );
                $deletedShop = self::sqlConcat(
                    'COALESCE('.self::sqlJsonText('security_audit_logs.metadata', 'name').", 'Barbería eliminada')",
                    "' (eliminada)'",
                );
                $genericSubject = self::sqlConcat("'Sujeto #'", self::sqlText('security_audit_logs.subject_id'));
                $expression = "CASE WHEN security_audit_logs.event IN ({$eventPlaceholders}) THEN CASE WHEN security_audit_logs.subject_id IS NULL THEN '—' WHEN {$userName} IS NOT NULL THEN {$userName} ELSE {$deletedAccount} END WHEN security_audit_logs.event LIKE 'platform.barbershop.%' THEN CASE WHEN security_audit_logs.subject_id IS NULL THEN '—' WHEN {$shopName} IS NOT NULL THEN {$shopName} ELSE {$deletedShop} END WHEN security_audit_logs.subject_id IS NULL THEN '—' ELSE {$genericSubject} END";

                $query->orderByRaw("{$expression} {$direction}", $userSubjectEvents);
            },
            'barbershop' => static function (Builder $query, string $direction): void {
                $loginEvents = ['logout', 'password.updated', 'password.reset', 'session.transfer_confirmed'];
                $loginPlaceholders = implode(', ', array_fill(0, count($loginEvents), '?'));
                $eventShop = '(SELECT barbershops.name FROM barbershops WHERE barbershops.id = security_audit_logs.barbershop_id)';
                $subjectShop = '(SELECT barbershops.name FROM barbershops WHERE barbershops.id = security_audit_logs.subject_id)';
                $deletedShop = self::sqlConcat(
                    'COALESCE('.self::sqlJsonText('security_audit_logs.metadata', 'name').", 'Barbería eliminada')",
                    "' (eliminada)'",
                );
                $expression = "CASE WHEN {$eventShop} IS NOT NULL THEN {$eventShop} WHEN security_audit_logs.event LIKE 'login.%' OR security_audit_logs.event IN ({$loginPlaceholders}) THEN 'Sin barbería asociada al evento' WHEN security_audit_logs.event LIKE 'platform.barbershop.%' AND security_audit_logs.subject_id IS NOT NULL THEN CASE WHEN {$subjectShop} IS NOT NULL THEN {$subjectShop} ELSE {$deletedShop} END WHEN security_audit_logs.barbershop_id IS NOT NULL THEN {$deletedShop} ELSE '—' END";

                $query->orderByRaw("{$expression} {$direction}", $loginEvents);
            },
            'metadata' => static function (Builder $query, string $direction): void {
                $metadataText = self::sqlText('security_audit_logs.metadata');
                $query->orderByRaw("CASE WHEN {$metadataText} IN ('[]', '{}') THEN '' ELSE {$metadataText} END {$direction}");
            },
            'ip_address' => static function (Builder $query, string $direction): void {
                $query->orderByRaw("COALESCE(security_audit_logs.ip_address, '—') {$direction}");
            },
            'user_agent' => static function (Builder $query, string $direction): void {
                $query->orderByRaw("COALESCE(security_audit_logs.user_agent, '—') {$direction}");
            },
        ];
        $sortState = TableSort::state($request, $sortColumns, 'created_at', 'desc');
        $auditQuery = SecurityAuditLog::query()
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
            ->orderByDesc('id');
        if ($sortState['requested']) {
            TableSort::apply($auditQuery, $sortColumns, $sortState['sort'], $sortState['direction']);
        }
        $auditLogs = $auditQuery->paginate(25)->withQueryString();

        $userSubjectIds = $auditLogs->getCollection()
            ->filter(fn (SecurityAuditLog $log): bool => $log->hasUserSubject() && $log->subject_id !== null)
            ->pluck('subject_id')->unique()->values();
        $usersById = $this->loadAccountUsers($userSubjectIds);

        $auditLogs->getCollection()->each(function (SecurityAuditLog $log) use ($usersById): void {
            $log->setRelation('subjectUser', $log->hasUserSubject() ? $usersById->get($log->subject_id) : null);
        });

        $securityAlerts = DB::table('security_alerts')
            ->leftJoin('users', 'security_alerts.subject_id', '=', 'users.id')
            ->leftJoin('barbershops', 'security_alerts.barbershop_id', '=', 'barbershops.id')
            ->select('security_alerts.*', 'users.name as account_name', 'users.email as account_email', 'barbershops.name as shop_name')
            ->orderByDesc('security_alerts.updated_at')->orderByDesc('security_alerts.id')->limit(20)->get();

        $data = [
            'auditLogs' => $auditLogs,
            'securityAlerts' => $securityAlerts,
            'sortState' => $sortState,
        ];
        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('platform.partials.security-audit-results', $data)->render(),
            ])->header('Cache-Control', 'private, no-store');
        }

        return view('platform.security-audit-logs', [
            ...$data,
            'auditPollEndpoint' => route('platform.security-audit-logs.index', array_merge($request->query(), ['page' => $request->query('page', 1)]), false),
        ]);
    }

    private static function sqlConcat(string ...$parts): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite', 'pgsql' => '('.implode(' || ', $parts).')',
            default => 'CONCAT('.implode(', ', $parts).')',
        };
    }

    private static function sqlText(string $expression): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "CAST({$expression} AS CHAR)",
            'sqlsrv' => "CAST({$expression} AS NVARCHAR(MAX))",
            default => "CAST({$expression} AS TEXT)",
        };
    }

    private static function sqlJsonText(string $expression, string $key): string
    {
        $path = '$.'.$key;

        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "JSON_UNQUOTE(JSON_EXTRACT({$expression}, '{$path}'))",
            'pgsql' => "({$expression} ->> '{$key}')",
            'sqlsrv' => "JSON_VALUE({$expression}, '{$path}')",
            default => "json_extract({$expression}, '{$path}')",
        };
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
