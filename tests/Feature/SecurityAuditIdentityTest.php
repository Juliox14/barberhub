<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecurityAuditIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_shows_known_accounts_and_distinct_current_shop_context(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);
        $actor = User::factory()->create(['name' => 'Audit Actor', 'email' => 'actor@example.test']);
        $subject = User::factory()->create(['name' => 'Audit Subject', 'email' => 'subject@example.test']);
        $eventShop = Barbershop::factory()->create(['name' => 'Event Shop']);
        $otherShop = Barbershop::factory()->create(['name' => 'Current Shop']);
        Membership::factory()->for($subject)->for($otherShop)->create(['status' => Membership::STATUS_ACTIVE]);
        $secondShop = Barbershop::factory()->create(['name' => 'Second Current Shop']);
        Membership::factory()->for($subject)->for($secondShop)->create(['status' => Membership::STATUS_ACTIVE]);
        $clientShop = Barbershop::factory()->create(['name' => 'Client Shop']);
        Customer::factory()->for($subject)->for($clientShop)->create();

        $this->insertAuditRecord([
            'event' => 'login.succeeded', 'actor_id' => $actor->id,
            'subject_id' => $subject->id, 'barbershop_id' => $eventShop->id,
        ]);

        $response = $this->actingAs($admin)->get('/platform/security-audit-logs');

        $response->assertOk()->assertSee('Audit Actor')->assertSee('actor@example.test')
            ->assertSee('Audit Subject')->assertSee('subject@example.test')
            ->assertSee('Event Shop')->assertSee('Current Shop')->assertSee('Second Current Shop')->assertSee('Cliente en')
            ->assertSee('Client Shop');
    }

    public function test_shop_event_subject_is_never_resolved_as_same_id_user(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);
        $sameIdUser = User::factory()->create(['name' => 'Unrelated Person', 'email' => 'unrelated@example.test']);
        $shopId = $sameIdUser->id;
        DB::table('barbershops')->insert([
            'id' => $shopId, 'name' => 'Deleted Shop Context', 'slug' => 'deleted-shop-context',
            'status' => Barbershop::STATUS_ACTIVE, 'timezone' => 'UTC',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->insertAuditRecord([
            'event' => 'platform.barbershop.deleted',
            'actor_id' => null,
            'subject_id' => $shopId,
            'barbershop_id' => null,
            'metadata' => json_encode(['name' => 'Deleted Shop Context'], JSON_THROW_ON_ERROR),
        ]);
        DB::table('barbershops')->where('id', $shopId)->delete();

        $response = $this->actingAs($admin)->get('/platform/security-audit-logs');

        $response->assertOk()->assertSee('Deleted Shop Context')
            ->assertDontSee('Unrelated Person')->assertDontSee('unrelated@example.test');
    }

    public function test_unknown_event_subject_does_not_infer_a_user_identity(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);
        $user = User::factory()->create(['name' => 'Unclassified Account', 'email' => 'unclassified@example.test']);
        $this->insertAuditRecord(['event' => 'custom.unknown', 'actor_id' => 999, 'subject_id' => $user->id]);

        $response = $this->actingAs($admin)->get('/platform/security-audit-logs');

        $response->assertOk()->assertSee('Sujeto #'.$user->id)->assertSee('Cuenta eliminada (#999)')
            ->assertDontSee('Unclassified Account')->assertDontSee('unclassified@example.test');
    }

    public function test_rendered_history_binds_polling_target_and_returns_replaceable_fragment(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);
        $this->insertAuditRecord(['event' => 'initial.rendered.event']);

        $pageResponse = $this->actingAs($admin)->get('/platform/security-audit-logs');
        $document = new \DOMDocument;
        $document->loadHTML($pageResponse->getContent(), LIBXML_NONET);
        $xpath = new \DOMXPath($document);
        $pollers = $xpath->query('//*[@data-audit-poller]');
        $targets = $xpath->query('//*[@data-audit-results]');
        $nestedTargets = $xpath->query('//*[@data-audit-poller]//*[@data-audit-results]');

        $this->assertSame(1, $pollers->length);
        $this->assertSame(1, $targets->length);
        $this->assertSame(1, $nestedTargets->length);
        $this->assertSame('/platform/security-audit-logs?page=1', $pollers->item(0)->getAttribute('data-endpoint'));
        $this->assertSame(1, $xpath->query('//*[@data-audit-results]//table')->length);
        $this->assertStringContainsString('initial.rendered.event', $targets->item(0)->textContent);

        foreach (range(1, 25) as $number) {
            $this->insertAuditRecord(['event' => 'older.rendered.event.'.$number]);
        }
        $this->insertAuditRecord(['event' => 'fresh.rendered.event']);

        $fragmentResponse = $this->withHeader('Accept', 'application/json')->get('/platform/security-audit-logs');
        $fragment = $fragmentResponse->json('html');

        $this->assertStringContainsString('27 registros disponibles', $fragment);
        $this->assertStringContainsString('<table', $fragment);
        $this->assertStringContainsString('fresh.rendered.event', $fragment);
        $this->assertStringContainsString('aria-label="Paginación de la bitácora"', $fragment);
        $this->assertStringNotContainsString('data-audit-results', $fragment);
    }

    public function test_json_refresh_remains_restricted_to_platform_administrators(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_platform_admin' => false]);
        $response = $this->actingAs($user)->withHeader('Accept', 'application/json')
            ->get('/platform/security-audit-logs');

        $response->assertForbidden()->assertDontSee('html');
    }

    public function test_auth_event_without_a_recorded_shop_does_not_guess_current_membership(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);
        $actor = User::factory()->create();
        $shop = Barbershop::factory()->create(['name' => 'Current Only Shop']);
        Membership::factory()->for($actor)->for($shop)->create(['status' => Membership::STATUS_ACTIVE]);
        $this->insertAuditRecord(['event' => 'login.succeeded', 'actor_id' => $actor->id, 'barbershop_id' => null]);

        $response = $this->actingAs($admin)->get('/platform/security-audit-logs');

        $response->assertOk()->assertSee('Sin barbería asociada al evento')->assertSee('Current Only Shop');
    }

    public function test_json_refresh_returns_safe_fragment_and_keeps_requested_page(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);
        foreach (range(1, 26) as $number) {
            $this->insertAuditRecord(['event' => 'refresh.event.'.$number]);
        }

        $this->actingAs($admin)->get('/platform/security-audit-logs?page=2')
            ->assertSee('data-endpoint="/platform/security-audit-logs?page=2"', false);

        $response = $this->withHeader('Accept', 'application/json')
            ->get('/platform/security-audit-logs?page=2');

        $response->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertJsonStructure(['html']);
        $this->assertStringContainsString('refresh.event.1</td>', $response->json('html'));
        $this->assertStringNotContainsString('refresh.event.26</td>', $response->json('html'));
        $this->assertStringContainsString('page=1', $response->json('html'));
        $this->assertStringNotContainsString('data-audit-results', $response->json('html'));
    }

    /** @param array<string, mixed> $overrides */
    private function insertAuditRecord(array $overrides = []): void
    {
        $now = now();
        DB::table('security_audit_logs')->insert([
            'event' => 'login.failed', 'actor_id' => null, 'subject_id' => null,
            'barbershop_id' => null, 'metadata' => '{}', 'ip_address' => null,
            'user_agent' => null, 'created_at' => $now, 'updated_at' => $now,
            ...$overrides,
        ]);
    }
}
