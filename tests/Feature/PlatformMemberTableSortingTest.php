<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PlatformMemberTableSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_allowlisted_member_table_sort_columns_are_accepted(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $shop = Barbershop::factory()->create();
        $member = User::factory()->create(['name' => 'Platform member']);
        Membership::factory()->for($shop)->for($member)->create();
        Customer::factory()->for($shop)->create(['email' => null, 'phone' => null]);

        foreach (['name', 'email', 'barbershop', 'role', 'status'] as $sort) {
            $this->actingAs($admin)
                ->get(route('platform.members.index', ['team_sort' => $sort, 'team_direction' => 'asc'], false))
                ->assertOk();
        }
        foreach (['name', 'email', 'phone', 'barbershop', 'account'] as $sort) {
            $this->get(route('platform.members.index', ['customers_sort' => $sort, 'customers_direction' => 'asc'], false))
                ->assertOk();
        }
    }

    public function test_member_tables_keep_independent_sorting_and_page_state_while_filtering(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $shop = Barbershop::factory()->create(['slug' => 'sort-shop']);
        Membership::factory()->count(16)->for($shop)->create();
        Customer::factory()->count(16)->for($shop)->create();

        $response = $this->actingAs($admin)
            ->get(route('platform.members.index', [
                'barbershop' => $shop->slug,
                'team_sort' => 'name', 'team_direction' => 'asc', 'team_page' => 2,
                'customers_sort' => 'name', 'customers_direction' => 'desc', 'customers_page' => 2,
            ], false))
            ->assertOk()
            ->assertViewHas('memberships', fn ($memberships): bool => $memberships->currentPage() === 2)
            ->assertViewHas('customers', fn ($customers): bool => $customers->currentPage() === 2)
            ->assertSee('barbershop='.$shop->slug, false)
            ->assertSee('team_sort=name', false)
            ->assertSee('customers_sort=name', false);

        $document = new DOMDocument;
        $document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $nameHeadings = $xpath->query('//a[contains(@aria-label, "Ordenar Nombre")]');
        $this->assertSame(2, $nameHeadings->length);
        $teamHref = $nameHeadings->item(0)->getAttribute('href');
        $customerHref = $nameHeadings->item(1)->getAttribute('href');
        parse_str((string) parse_url($teamHref, PHP_URL_QUERY), $teamQuery);
        parse_str((string) parse_url($customerHref, PHP_URL_QUERY), $customerQuery);

        $this->assertArrayNotHasKey('team_page', $teamQuery);
        $this->assertSame('2', $teamQuery['customers_page']);
        $this->assertSame('2', $customerQuery['team_page']);
        $this->assertArrayNotHasKey('customers_page', $customerQuery);
    }

    public function test_member_tables_sort_related_and_nullable_values_by_their_displayed_type(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $shop = Barbershop::factory()->create(['name' => 'Central']);
        $alpha = User::factory()->create(['name' => 'Alpha Member', 'email' => 'alpha@example.com']);
        $zeta = User::factory()->create(['name' => 'Zeta Member', 'email' => 'zeta@example.com']);
        Membership::factory()->for($shop)->for($zeta)->create();
        Membership::factory()->for($shop)->for($alpha)->create();
        Customer::factory()->for($shop)->create(['name' => 'First Customer', 'email' => null]);
        Customer::factory()->for($shop)->create(['name' => 'Second Customer', 'email' => 'customer@example.com']);

        $this->actingAs($admin)
            ->get(route('platform.members.index', [
                'team_sort' => 'name', 'team_direction' => 'asc',
                'customers_sort' => 'email', 'customers_direction' => 'desc',
            ], false))
            ->assertOk()
            ->assertViewHas('memberships', fn ($memberships): bool => $memberships->getCollection()->pluck('user.name')->all() === ['Alpha Member', 'Zeta Member'])
            ->assertViewHas('customers', fn ($customers): bool => $customers->first()->email === 'customer@example.com');
    }

    public function test_membership_role_and_status_sorts_follow_the_rendered_labels(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $shop = Barbershop::factory()->create();
        foreach ([
            ['role' => Membership::ROLE_OWNER, 'status' => Membership::STATUS_INACTIVE],
            ['role' => Membership::ROLE_BARBER, 'status' => Membership::STATUS_ACTIVE],
            ['role' => Membership::ROLE_ADMIN, 'status' => Membership::STATUS_ACTIVE],
        ] as $attributes) {
            Membership::factory()->for($shop)->create($attributes);
        }

        $roleResponse = $this->actingAs($admin)
            ->get(route('platform.members.index', ['team_sort' => 'role', 'team_direction' => 'asc'], false))
            ->assertOk();
        $this->assertSame(['admin', 'barber', 'owner'], $roleResponse->viewData('memberships')->getCollection()->pluck('role')->all());
        $this->assertSame(['Administrador', 'Barbero', 'Dueño'], $this->memberColumnValues($roleResponse, 4));

        $statusResponse = $this->get(route('platform.members.index', ['team_sort' => 'status', 'team_direction' => 'asc'], false))
            ->assertOk();
        $this->assertSame(['active', 'active', 'inactive'], $statusResponse->viewData('memberships')->getCollection()->pluck('status')->all());
        $this->assertSame(['Activo', 'Activo', 'Inactivo'], $this->memberColumnValues($statusResponse, 5));
    }

    /** @return list<string> */
    private function memberColumnValues(TestResponse $response, int $column): array
    {
        $document = new DOMDocument;
        $document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $cells = $xpath->query('//table[1]/tbody/tr/td['.$column.']');

        return array_map(static fn ($cell): string => trim($cell->textContent), iterator_to_array($cells));
    }
}
