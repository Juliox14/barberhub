<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformTableSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_sort_orders_only_the_existing_recent_six_records(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        foreach (range(1, 7) as $number) {
            Barbershop::factory()->create([
                'name' => sprintf('Shop %02d', $number),
                'country_code' => match ($number) {
                    2 => 'DE',
                    3 => 'AD',
                    default => null,
                },
                'created_at' => now()->addSeconds($number),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('platform.dashboard', ['sort' => 'name', 'direction' => 'asc'], false))
            ->assertOk()
            ->assertViewHas('barbershops', function ($barbershops): bool {
                return $barbershops->count() === 6
                    && $barbershops->first()->name === 'Shop 02'
                    && $barbershops->last()->name === 'Shop 07';
            })
            ->assertDontSee('Shop 01');

        $this->get(route('platform.dashboard', ['sort' => 'country', 'direction' => 'asc'], false))
            ->assertOk()
            ->assertViewHas('barbershops', function ($barbershops): bool {
                $this->assertSame('Shop 02', $barbershops->first()->name);

                return true;
            })
            ->assertSee('País')
            ->assertSee('Estado/Región')
            ->assertSee('Ciudad')
            ->assertSee('—');
    }

    public function test_barbershop_table_sorts_whole_result_by_spanish_country_name_and_shows_locations(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        Barbershop::factory()->create([
            'name' => 'Shop Alemania', 'country_code' => 'DE', 'region' => 'Baviera', 'city' => 'Múnich',
        ]);
        Barbershop::factory()->create([
            'name' => 'Shop Andorra', 'country_code' => 'AD', 'region' => 'Andorra', 'city' => 'Andorra la Vieja',
        ]);
        foreach (range(1, 9) as $number) {
            Barbershop::factory()->create([
                'name' => sprintf('Shop Extra %02d', $number), 'country_code' => 'MX',
            ]);
        }

        $this->actingAs($admin)
            ->get(route('platform.barbershops.index', ['sort' => 'country', 'direction' => 'asc', 'page' => 1], false))
            ->assertOk()
            ->assertViewHas('barbershops', function ($barbershops): bool {
                return $barbershops->currentPage() === 1
                    && $barbershops->first()->name === 'Shop Alemania';
            })
            ->assertSee('País')
            ->assertSee('Estado/Región')
            ->assertSee('Ciudad')
            ->assertSee('Baviera')
            ->assertSee('Múnich')
            ->assertSee('—');
    }

    public function test_unrecognized_sort_columns_keep_the_platform_default_order(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        Barbershop::factory()->create(['name' => 'Oldest Shop', 'created_at' => now()->subDay()]);
        Barbershop::factory()->create(['name' => 'Newest Shop', 'created_at' => now()]);

        $this->actingAs($admin)
            ->get(route('platform.barbershops.index', [
                'sort' => 'barbershops.name;drop', 'direction' => 'asc',
            ], false))
            ->assertOk()
            ->assertViewHas('barbershops', fn ($barbershops): bool => $barbershops->first()->name === 'Newest Shop')
            ->assertViewHas('sortState', fn (array $sortState): bool => $sortState['sort'] === 'created_at' && $sortState['direction'] === 'desc');
    }

    public function test_allowlisted_dashboard_and_barbershop_sort_columns_are_accepted(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $shop = Barbershop::factory()->create([
            'country_code' => 'MX', 'region' => 'Jalisco', 'city' => 'Guadalajara',
        ]);
        $this->actingAs($admin);

        foreach (['name', 'status', 'timezone', 'country', 'region', 'city', 'members', 'created_at'] as $sort) {
            $this->get(route('platform.dashboard', ['sort' => $sort, 'direction' => 'asc'], false))
                ->assertOk();
        }
        foreach (['name', 'slug', 'country', 'region', 'city', 'timezone', 'status', 'created_at'] as $sort) {
            $this->get(route('platform.barbershops.index', ['sort' => $sort, 'direction' => 'asc'], false))
                ->assertOk();
        }
    }

    public function test_numeric_membership_counts_sort_in_both_directions(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $smallShop = Barbershop::factory()->create(['name' => 'Two Members']);
        $largeShop = Barbershop::factory()->create(['name' => 'Ten Members']);
        Membership::factory()->count(2)->for($smallShop)->create();
        Membership::factory()->count(10)->for($largeShop)->create();
        $this->actingAs($admin)
            ->get(route('platform.dashboard', ['sort' => 'members', 'direction' => 'asc'], false))
            ->assertViewHas('barbershops', fn ($shops): bool => $shops->first()->name === 'Two Members');
        $this->get(route('platform.dashboard', ['sort' => 'members', 'direction' => 'desc'], false))
            ->assertViewHas('barbershops', fn ($shops): bool => $shops->first()->name === 'Ten Members');
    }

    public function test_barbershop_dates_sort_by_calendar_date_in_both_directions(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        Barbershop::factory()->create(['name' => 'Older Date', 'created_at' => '2020-01-02 00:00:00']);
        Barbershop::factory()->create(['name' => 'Newer Date', 'created_at' => '2023-12-01 00:00:00']);

        $this->actingAs($admin)
            ->get(route('platform.barbershops.index', ['sort' => 'created_at', 'direction' => 'asc'], false))
            ->assertViewHas('barbershops', fn ($shops): bool => $shops->first()->name === 'Older Date');
        $this->get(route('platform.barbershops.index', ['sort' => 'created_at', 'direction' => 'desc'], false))
            ->assertViewHas('barbershops', fn ($shops): bool => $shops->first()->name === 'Newer Date');
    }
}
