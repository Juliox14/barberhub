<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SecurityInterfaceInteractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_pages_have_independent_native_disclosures_with_their_content_and_admin_navigation(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $shop = Barbershop::factory()->create();
        $this->actingAs($admin);

        $containment = $this->get(route('platform.security.index'))->assertOk();
        $dom = $this->dom($containment);
        foreach (['sessions', 'modules'] as $section) {
            $this->assertSame(1, $dom->query('//details[@data-security-section="'.$section.'" and @open]/summary')->length);
            $this->assertGreaterThan(0, $dom->query('//details[@data-security-section="'.$section.'"]//form[@method="POST"]')->length);
        }
        $containment->assertSee($shop->name)->assertSee($admin->email);
        $this->assertSecurityNavigation($dom, 'platform', route('platform.security.index'));

        $audit = $this->get(route('platform.security-audit-logs.index'))->assertOk();
        $dom = $this->dom($audit);
        foreach (['alerts', 'audit-log'] as $section) {
            $this->assertSame(1, $dom->query('//details[@data-security-section="'.$section.'" and @open]/summary')->length);
        }
        $this->assertSame(1, $dom->query('//details[@data-security-section="audit-log"]//table')->length);
        $this->assertSecurityNavigation($dom, 'platform', route('platform.security-audit-logs.index'));

        $fragment = $this->getJson(route('platform.security-audit-logs.index'))->assertOk()->assertJsonStructure(['html']);
        $this->assertStringContainsString('data-security-section="alerts"', $fragment->json('html'));
        $this->assertStringContainsString('data-security-section="audit-log"', $fragment->json('html'));
    }

    public function test_account_navigation_groups_both_security_links_on_desktop_and_mobile_only_for_admins(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $dom = $this->dom($this->actingAs($admin)->get(route('profile.edit'))->assertOk());
        foreach (['desktop', 'mobile'] as $surface) {
            $this->assertSecurityNavigation($dom, $surface);
        }

        $member = User::factory()->create();
        $profile = $this->actingAs($member)->get(route('profile.edit'))->assertOk();
        $this->assertSame(0, $this->dom($profile)->query('//details[@data-security-navigation]')->length);
        $profile->assertDontSee(route('platform.security.index'), false)->assertDontSee(route('platform.security-audit-logs.index'), false);
        $this->get(route('platform.security.index'))->assertForbidden();
        $this->get(route('platform.security-audit-logs.index'))->assertForbidden();
        $this->getJson(route('platform.security-audit-logs.index'))->assertForbidden();
    }

    private function assertSecurityNavigation(DOMXPath $dom, string $surface, ?string $activeUrl = null): void
    {
        $group = '//details[@data-security-navigation="'.$surface.'"]';
        $this->assertSame(1, $dom->query($group.'/summary[contains(., "Seguridad")]')->length);
        if ($surface === 'platform') {
            $this->assertSame('openSecurityGroup($event)', $dom->query($group.'/summary')->item(0)->getAttribute('x-on:click'));
        }
        foreach (['platform.security.index', 'platform.security-audit-logs.index'] as $route) {
            $this->assertSame(1, $dom->query($group.'//a[@href="'.route($route).'"]')->length);
        }
        if ($activeUrl !== null) {
            $this->assertSame(1, $dom->query($group.'//a[@href="'.$activeUrl.'" and @aria-current="page"]')->length);
        }
    }

    private function dom(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());

        return new DOMXPath($document);
    }
}
