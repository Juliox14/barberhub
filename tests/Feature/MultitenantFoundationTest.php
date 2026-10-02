<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\CustomerPreference;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MultitenantFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'tenant.member'])->get('/tenant-test/{barbershop:slug}', function (Barbershop $barbershop) {
            return response()->json(['barbershop' => $barbershop->slug]);
        })->name('tenant-test.show');
    }

    public function test_customer_can_be_created_without_linked_user(): void
    {
        $barbershop = Barbershop::factory()->create();

        $customer = Customer::factory()->for($barbershop)->create([
            'user_id' => null,
        ]);

        $this->assertNull($customer->user_id);
        $this->assertTrue($customer->barbershop->is($barbershop));
    }

    public function test_customer_preferences_are_one_to_one_by_customer(): void
    {
        $customer = Customer::factory()->create();

        $preference = CustomerPreference::factory()->for($customer)->create([
            'preferences' => ['notes' => 'prefers mornings'],
        ]);

        $this->assertTrue($customer->preference->is($preference));
        $this->assertDatabaseHas('customer_preferences', [
            'customer_id' => $customer->id,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        CustomerPreference::factory()->for($customer)->create();
    }

    public function test_user_with_membership_can_access_tenant_protected_route(): void
    {
        $user = User::factory()->create();
        $barbershop = Barbershop::factory()->create();

        Membership::factory()->for($user)->for($barbershop)->create();

        $this->actingAs($user)
            ->get("/tenant-test/{$barbershop->slug}")
            ->assertOk()
            ->assertJsonPath('barbershop', $barbershop->slug);
    }

    public function test_user_without_membership_cannot_access_tenant_protected_route(): void
    {
        $user = User::factory()->create();
        $barbershop = Barbershop::factory()->create();

        $this->actingAs($user)
            ->get("/tenant-test/{$barbershop->slug}")
            ->assertForbidden();
    }

    public function test_user_with_membership_in_different_barbershop_cannot_access_this_barbershop(): void
    {
        $user = User::factory()->create();
        $allowedBarbershop = Barbershop::factory()->create();
        $blockedBarbershop = Barbershop::factory()->create();

        Membership::factory()->for($user)->for($allowedBarbershop)->create();

        $this->actingAs($user)
            ->get("/tenant-test/{$blockedBarbershop->slug}")
            ->assertForbidden();
    }

    public function test_platform_admin_can_access_tenant_protected_route(): void
    {
        $platformAdmin = User::factory()->create([
            'is_platform_admin' => true,
        ]);
        $barbershop = Barbershop::factory()->create();

        $this->actingAs($platformAdmin)
            ->get("/tenant-test/{$barbershop->slug}")
            ->assertOk();
    }

    public function test_membership_roles_persist_per_barbershop_for_same_user(): void
    {
        $user = User::factory()->create();
        $firstBarbershop = Barbershop::factory()->create();
        $secondBarbershop = Barbershop::factory()->create();

        Membership::factory()->for($user)->for($firstBarbershop)->create([
            'role' => Membership::ROLE_OWNER,
        ]);
        Membership::factory()->for($user)->for($secondBarbershop)->create([
            'role' => Membership::ROLE_BARBER,
        ]);

        $this->assertDatabaseHas('memberships', [
            'user_id' => $user->id,
            'barbershop_id' => $firstBarbershop->id,
            'role' => Membership::ROLE_OWNER,
        ]);
        $this->assertDatabaseHas('memberships', [
            'user_id' => $user->id,
            'barbershop_id' => $secondBarbershop->id,
            'role' => Membership::ROLE_BARBER,
        ]);
    }
}
