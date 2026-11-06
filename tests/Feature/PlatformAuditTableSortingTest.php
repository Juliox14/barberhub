<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PlatformAuditTableSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_allowlisted_audit_sort_columns_are_accepted(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $shop = Barbershop::factory()->create();
        $this->insertAuditLog([
            'actor_id' => $admin->id,
            'subject_id' => $shop->id,
            'barbershop_id' => $shop->id,
            'event' => 'platform.barbershop.created',
            'metadata' => json_encode(['name' => $shop->name], JSON_THROW_ON_ERROR),
        ]);

        foreach (['created_at', 'event', 'actor', 'subject', 'barbershop', 'metadata', 'ip_address', 'user_agent'] as $sort) {
            $this->actingAs($admin)
                ->get(route('platform.security-audit-logs.index', ['sort' => $sort, 'direction' => 'asc'], false))
                ->assertOk();
        }
    }

    public function test_subject_sort_uses_the_event_specific_rendered_subject_when_ids_collide(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        Barbershop::factory()->create(['name' => 'Other Shop']);
        $matchingShop = Barbershop::factory()->create(['name' => 'Aardvark Barbería']);
        $subjectUser = User::factory()->create(['name' => 'Zebra Account']);
        $this->assertSame($matchingShop->id, $subjectUser->id);

        $this->insertAuditLog(['event' => 'tenant.membership.created', 'subject_id' => $subjectUser->id]);
        $this->insertAuditLog([
            'event' => 'platform.barbershop.deleted', 'subject_id' => $matchingShop->id,
            'metadata' => json_encode(['name' => $matchingShop->name], JSON_THROW_ON_ERROR),
        ]);
        $this->insertAuditLog(['event' => 'login.succeeded', 'subject_id' => null]);

        $response = $this->actingAs($admin)
            ->get(route('platform.security-audit-logs.index', ['sort' => 'subject', 'direction' => 'asc'], false))
            ->assertOk();

        $this->assertSame(
            ['platform.barbershop.deleted', 'tenant.membership.created', 'login.succeeded'],
            $this->auditCellValues($response, 2),
        );
        $subjects = $this->auditCellValues($response, 4);
        $this->assertStringContainsString('Aardvark Barbería', $subjects[0]);
        $this->assertStringContainsString('Zebra Account', $subjects[1]);
        $this->assertSame('—', $subjects[2]);
    }

    public function test_event_shop_sort_uses_rendered_shop_name_or_placeholder_when_ids_collide(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $barbershop = Barbershop::factory()->create(['name' => 'Zulu Barbería']);
        $otherShop = Barbershop::factory()->create(['name' => 'Aardvark Barbería']);
        $subjectUser = User::factory()->create(['name' => 'Other Account']);
        $this->assertSame($otherShop->id, $subjectUser->id);

        $this->insertAuditLog(['event' => 'tenant.membership.created', 'subject_id' => $subjectUser->id]);
        $this->insertAuditLog([
            'event' => 'platform.barbershop.updated', 'subject_id' => $barbershop->id,
            'barbershop_id' => $barbershop->id,
            'metadata' => json_encode(['name' => $barbershop->name], JSON_THROW_ON_ERROR),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('platform.security-audit-logs.index', ['sort' => 'barbershop', 'direction' => 'asc'], false))
            ->assertOk();

        $this->assertSame(
            ['platform.barbershop.updated', 'tenant.membership.created'],
            $this->auditCellValues($response, 2),
        );
        $this->assertStringContainsString('Zulu Barbería', $this->auditCellValues($response, 5)[0]);
        $this->assertSame('—', $this->auditCellValues($response, 5)[1]);
    }

    public function test_actor_sort_orders_rendered_guest_placeholder_with_account_names(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $zulu = User::factory()->create(['name' => 'Zulu Actor']);
        $aaron = User::factory()->create(['name' => 'Aaron Actor']);
        $this->insertAuditLog(['event' => 'logout', 'actor_id' => $zulu->id]);
        $this->insertAuditLog(['event' => 'logout', 'actor_id' => 999]);
        $this->insertAuditLog(['event' => 'logout', 'actor_id' => null]);
        $this->insertAuditLog(['event' => 'logout', 'actor_id' => $aaron->id]);

        $response = $this->actingAs($admin)
            ->get(route('platform.security-audit-logs.index', ['sort' => 'actor', 'direction' => 'asc'], false))
            ->assertOk();

        $actors = $this->auditCellValues($response, 3);
        $this->assertStringStartsWith('Aaron Actor', $actors[0]);
        $this->assertStringStartsWith('Cuenta eliminada (#999)', $actors[1]);
        $this->assertStringStartsWith('Invitado', $actors[2]);
        $this->assertStringStartsWith('Zulu Actor', $actors[3]);
    }

    public function test_metadata_sorts_as_stable_json_text_in_both_directions(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->insertAuditLog([
            'event' => 'zeta.metadata', 'metadata' => json_encode(['name' => 'Zeta'], JSON_THROW_ON_ERROR),
            'created_at' => '2025-01-02 00:00:00',
        ]);
        $this->insertAuditLog([
            'event' => 'alpha.metadata', 'metadata' => json_encode(['name' => 'Alpha'], JSON_THROW_ON_ERROR),
            'created_at' => '2025-01-01 00:00:00',
        ]);

        $ascending = $this->actingAs($admin)
            ->get(route('platform.security-audit-logs.index', ['sort' => 'metadata', 'direction' => 'asc'], false))
            ->assertOk();
        $this->assertSame(['alpha.metadata', 'zeta.metadata'], $this->auditCellValues($ascending, 2));
        $this->assertStringContainsString('Alpha', $this->auditCellValues($ascending, 6)[0]);
        $this->assertStringContainsString('data-sort="metadata"', $ascending->getContent());

        $descending = $this->get(route('platform.security-audit-logs.index', ['sort' => 'metadata', 'direction' => 'desc'], false))
            ->assertOk();
        $this->assertSame(['zeta.metadata', 'alpha.metadata'], $this->auditCellValues($descending, 2));
    }

    public function test_audit_sort_and_filters_are_preserved_in_polling_endpoint(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        foreach (['zeta.event', 'alpha.event'] as $event) {
            DB::table('security_audit_logs')->insert([
                'event' => $event,
                'metadata' => '[]',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('platform.security-audit-logs.index', [
                'sort' => 'event', 'direction' => 'asc', 'page' => 1,
            ], false))
            ->assertOk()
            ->assertViewHas('auditLogs', fn ($auditLogs): bool => $auditLogs->getCollection()->pluck('event')->all() === ['alpha.event', 'zeta.event'])
            ->assertSee('data-endpoint="/platform/security-audit-logs?sort=event&amp;direction=asc&amp;page=1"', false)
            ->assertSee('data-sort="event"', false);
    }

    /** @param array<string, mixed> $attributes */
    private function insertAuditLog(array $attributes): void
    {
        DB::table('security_audit_logs')->insert(array_merge([
            'actor_id' => null,
            'subject_id' => null,
            'barbershop_id' => null,
            'event' => 'login.failed',
            'metadata' => '[]',
            'ip_address' => null,
            'user_agent' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    /** @return list<string> */
    private function auditCellValues(TestResponse $response, int $column): array
    {
        $document = new DOMDocument;
        $document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $rows = $xpath->query('//details[@data-security-section="audit-log"]//tbody/tr[td]');

        return array_map(function ($row) use ($xpath, $column): string {
            $text = $xpath->query('./td['.$column.']', $row)->item(0)->textContent;

            return trim((string) preg_replace('/\\s+/', ' ', $text));
        }, iterator_to_array($rows));
    }
}
