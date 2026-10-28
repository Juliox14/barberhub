<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecurityAuditHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_requires_an_authenticated_verified_platform_admin(): void
    {
        $path = '/platform/security-audit-logs';
        $this->get($path)->assertRedirect('/login');

        $user = User::factory()->create(['email_verified_at' => now(), 'is_platform_admin' => false]);
        $this->actingAs($user)->get($path)->assertForbidden();

        $middleware = app('router')->getRoutes()->getByName('platform.security-audit-logs.index')->gatherMiddleware();
        $this->assertContains('auth', $middleware);
        $this->assertContains('verified', $middleware);
        $this->assertContains('platform.admin', $middleware);
    }

    public static function activeTenantMembershipRoles(): array
    {
        return [
            'owner' => [Membership::ROLE_OWNER],
            'admin' => [Membership::ROLE_ADMIN],
            'barber' => [Membership::ROLE_BARBER],
        ];
    }

    #[DataProvider('activeTenantMembershipRoles')]
    public function test_active_tenant_members_cannot_view_platform_audit_history(string $role): void
    {
        $barbershop = Barbershop::factory()->create();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_platform_admin' => false,
        ]);
        Membership::factory()->for($user)->for($barbershop)->create([
            'role' => $role,
            'status' => Membership::STATUS_ACTIVE,
        ]);
        $this->insertAuditRecord(['event' => 'private-audit-entry']);

        $response = $this->actingAs($user)->get(route('platform.security-audit-logs.index', absolute: false));

        $response->assertForbidden()->assertDontSee('private-audit-entry');
    }

    public function test_platform_admin_sees_escaped_read_only_audit_history(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);
        $untrustedValue = '<script>alert("audit-xss")</script>';
        $untrustedAgent = '<img src=x onerror=alert(1)>';
        $this->insertAuditRecord([
            'event' => 'platform.barbershop.updated',
            'metadata' => json_encode(['name' => $untrustedValue], JSON_THROW_ON_ERROR),
            'ip_address' => '192.0.2.4<script>',
            'user_agent' => $untrustedAgent,
        ]);

        $response = $this->actingAs($admin)->get('/platform/security-audit-logs');

        $response->assertOk()
            ->assertSee('Bitácora de seguridad')
            ->assertSee('&lt;script&gt;', false)
            ->assertSee('&lt;/script&gt;', false)
            ->assertSee('&lt;img', false)
            ->assertSee('192.0.2.4&lt;script&gt;', false)
            ->assertDontSee($untrustedValue, false)
            ->assertDontSee($untrustedAgent, false);
        $this->post('/platform/security-audit-logs')->assertMethodNotAllowed();
        $this->put('/platform/security-audit-logs/1')->assertNotFound();
        $this->delete('/platform/security-audit-logs/1')->assertNotFound();
    }

    public function test_history_is_paginated_newest_first_with_stable_order(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);
        $timestamp = now()->subMinute();

        foreach (range(1, 26) as $number) {
            $this->insertAuditRecord([
                'event' => 'audit.event.'.$number,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $firstPage = $this->actingAs($admin)->get('/platform/security-audit-logs');
        $firstPage->assertOk()
            ->assertSee('audit.event.26</td>', false)
            ->assertDontSee('audit.event.1</td>', false);
        $firstPage->assertSee('Siguiente')
            ->assertSee('?page=2', false);
    }

    /** @param array<string, mixed> $overrides */
    private function insertAuditRecord(array $overrides = []): void
    {
        $now = now();
        DB::table('security_audit_logs')->insert([
            'event' => 'login.failed',
            'actor_id' => null,
            'subject_id' => null,
            'barbershop_id' => null,
            'metadata' => '{}',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test agent',
            'created_at' => $now,
            'updated_at' => $now,
            ...$overrides,
        ]);
    }
}
