<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantMembershipManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_admin_can_view_members(): void
    {
        $barbershop = Barbershop::factory()->create(['name' => 'Barbería Centro']);
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $barber = User::factory()->create(['name' => 'Carlos Barbero']);
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        Membership::factory()->for($admin)->for($barbershop)->create(['role' => Membership::ROLE_ADMIN]);
        Membership::factory()->for($barber)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);

        $this->actingAs($owner)
            ->get(route('tenant.members.index', $barbershop, false))
            ->assertOk()
            ->assertSee('Miembros de Barbería Centro')
            ->assertSee('Carlos Barbero');

        $this->actingAs($admin)
            ->get(route('tenant.members.index', $barbershop, false))
            ->assertOk()
            ->assertSee('Miembros de Barbería Centro')
            ->assertSee('Carlos Barbero');
    }

    public function test_barber_cannot_manage_members(): void
    {
        $barbershop = Barbershop::factory()->create();
        $barber = User::factory()->create();
        $newMember = User::factory()->create(['email' => 'nuevo@example.com']);
        Membership::factory()->for($barber)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);

        $this->actingAs($barber)
            ->get(route('tenant.members.index', $barbershop, false))
            ->assertForbidden();

        $this->actingAs($barber)
            ->post(route('tenant.members.store', $barbershop, false), [
                'email' => $newMember->email,
                'role' => Membership::ROLE_BARBER,
                'status' => Membership::STATUS_ACTIVE,
            ])->assertForbidden();

        $this->assertDatabaseMissing('memberships', [
            'user_id' => $newMember->id,
            'barbershop_id' => $barbershop->id,
        ]);
    }

    public function test_user_from_another_barbershop_cannot_access_member_management(): void
    {
        $user = User::factory()->create();
        $allowedBarbershop = Barbershop::factory()->create();
        $blockedBarbershop = Barbershop::factory()->create();
        Membership::factory()->for($user)->for($allowedBarbershop)->create(['role' => Membership::ROLE_OWNER]);

        $this->actingAs($user)
            ->get(route('tenant.members.index', $blockedBarbershop, false))
            ->assertForbidden();
    }

    public function test_owner_and_admin_can_add_existing_user_by_email(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $existingUser = User::factory()->create(['email' => 'barbera@example.com']);
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);

        $response = $this->actingAs($owner)
            ->post(route('tenant.members.store', $barbershop, false), [
                'email' => $existingUser->email,
                'role' => Membership::ROLE_ADMIN,
            ]);

        $response->assertRedirect(route('tenant.members.index', $barbershop, false));

        $this->assertDatabaseHas('memberships', [
            'user_id' => $existingUser->id,
            'barbershop_id' => $barbershop->id,
            'role' => Membership::ROLE_ADMIN,
            'status' => Membership::STATUS_ACTIVE,
        ]);
    }

    public function test_adding_duplicate_updates_existing_membership_instead_of_creating_duplicate(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'duplicado@example.com']);
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        Membership::factory()->for($member)->for($barbershop)->create([
            'role' => Membership::ROLE_BARBER,
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $this->actingAs($owner)
            ->post(route('tenant.members.store', $barbershop, false), [
                'email' => $member->email,
                'role' => Membership::ROLE_ADMIN,
                'status' => Membership::STATUS_INACTIVE,
            ])
            ->assertRedirect(route('tenant.members.index', $barbershop, false));

        $this->assertDatabaseCount('memberships', 2);
        $this->assertDatabaseHas('memberships', [
            'user_id' => $member->id,
            'barbershop_id' => $barbershop->id,
            'role' => Membership::ROLE_ADMIN,
            'status' => Membership::STATUS_INACTIVE,
        ]);
    }

    public function test_owner_and_admin_can_update_role_and_status(): void
    {
        $barbershop = Barbershop::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $membership = Membership::factory()->for($member)->for($barbershop)->create([
            'role' => Membership::ROLE_BARBER,
            'status' => Membership::STATUS_ACTIVE,
        ]);
        Membership::factory()->for($admin)->for($barbershop)->create(['role' => Membership::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->put(route('tenant.members.update', [$barbershop, $membership], false), [
                'role' => Membership::ROLE_ADMIN,
                'status' => Membership::STATUS_INACTIVE,
            ])
            ->assertRedirect(route('tenant.members.index', $barbershop, false));

        $this->assertDatabaseHas('memberships', [
            'id' => $membership->id,
            'role' => Membership::ROLE_ADMIN,
            'status' => Membership::STATUS_INACTIVE,
        ]);
    }

    public function test_cannot_demote_deactivate_or_delete_the_last_active_owner(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $ownerMembership = Membership::factory()->for($owner)->for($barbershop)->create([
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $this->actingAs($owner)
            ->from(route('tenant.members.index', $barbershop, false))
            ->put(route('tenant.members.update', [$barbershop, $ownerMembership], false), [
                'role' => Membership::ROLE_ADMIN,
                'status' => Membership::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('tenant.members.index', $barbershop, false))
            ->assertSessionHasErrors('owner');

        $this->assertDatabaseHas('memberships', [
            'id' => $ownerMembership->id,
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $this->actingAs($owner)
            ->from(route('tenant.members.index', $barbershop, false))
            ->put(route('tenant.members.update', [$barbershop, $ownerMembership], false), [
                'role' => Membership::ROLE_OWNER,
                'status' => Membership::STATUS_INACTIVE,
            ])
            ->assertRedirect(route('tenant.members.index', $barbershop, false))
            ->assertSessionHasErrors('owner');

        $this->actingAs($owner)
            ->from(route('tenant.members.index', $barbershop, false))
            ->delete(route('tenant.members.destroy', [$barbershop, $ownerMembership], false))
            ->assertRedirect(route('tenant.members.index', $barbershop, false))
            ->assertSessionHasErrors('owner');

        $this->assertDatabaseHas('memberships', [
            'id' => $ownerMembership->id,
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
        ]);
    }

    public function test_membership_route_cannot_mutate_membership_from_another_barbershop(): void
    {
        $barbershop = Barbershop::factory()->create();
        $otherBarbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $otherMember = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $otherMembership = Membership::factory()->for($otherMember)->for($otherBarbershop)->create([
            'role' => Membership::ROLE_BARBER,
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $this->actingAs($owner)
            ->put(route('tenant.members.update', [$barbershop, $otherMembership], false), [
                'role' => Membership::ROLE_ADMIN,
                'status' => Membership::STATUS_INACTIVE,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('memberships', [
            'id' => $otherMembership->id,
            'role' => Membership::ROLE_BARBER,
            'status' => Membership::STATUS_ACTIVE,
        ]);
    }

    public function test_platform_admin_can_access_and_manage_members_for_support(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $barbershop = Barbershop::factory()->create(['name' => 'Barbería Soporte']);
        $existingUser = User::factory()->create(['email' => 'soporte-miembro@example.com']);

        $this->actingAs($platformAdmin)
            ->get(route('tenant.members.index', $barbershop, false))
            ->assertOk()
            ->assertSee('Miembros de Barbería Soporte');

        $this->actingAs($platformAdmin)
            ->post(route('tenant.members.store', $barbershop, false), [
                'email' => $existingUser->email,
                'role' => Membership::ROLE_BARBER,
                'status' => Membership::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('tenant.members.index', $barbershop, false));

        $this->assertDatabaseHas('memberships', [
            'user_id' => $existingUser->id,
            'barbershop_id' => $barbershop->id,
            'role' => Membership::ROLE_BARBER,
            'status' => Membership::STATUS_ACTIVE,
        ]);
    }
}
