<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\BarberAvailability;
use App\Models\Barbershop;
use App\Models\BusinessHour;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TenantDashboardPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_service_update_reopens_its_editor_with_old_values_and_keeps_create_form_separate(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $service = Service::factory()->for($barbershop)->create([
            'name' => 'Corte original',
            'description' => 'Descripción original',
            'price' => 20,
            'duration_minutes' => 30,
        ]);
        $otherService = Service::factory()->for($barbershop)->create([
            'name' => 'Barba original',
            'description' => 'Perfilado original',
            'price' => 15,
            'duration_minutes' => 20,
        ]);
        $editContext = 'service-'.$service->id;

        $this->actingAs($owner)
            ->from(route('tenant.services.index', $barbershop, false))
            ->put(route('tenant.services.update', [$barbershop, $service], false), [
                'form_context' => $editContext,
                'name' => 'Corte corregido',
                'description' => 'Descripción corregida',
                'price' => '-1',
                'duration_minutes' => 45,
                'active' => true,
            ])
            ->assertRedirect(route('tenant.services.index', $barbershop, false))
            ->assertSessionHasErrors('price');

        $response = $this->get(route('tenant.services.index', $barbershop, false));
        $response->assertOk()
            ->assertSee('id="service-'.$service->id.'-title"', false)
            ->assertSee('value="'.$editContext.'"', false)
            ->assertSee('value="Corte corregido"', false)
            ->assertSee('action="'.route('tenant.services.update', [$barbershop, $service]).'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('form="service-update-'.$service->id.'"', false)
            ->assertSee('id="name-'.$otherService->id.'" name="name" type="text" value="'.$otherService->name.'"', false);
        $this->assertOnlySlideOverOpen($response, $editContext);
        $response->assertSee('form="service-update-'.$service->id.'"', false);

        $createForm = $this->formMarkup($response, 'service-create');
        $this->assertStringNotContainsString('Corte corregido', $createForm);
        $this->assertStringContainsString('id="name" name="name" type="text" value=""', $createForm);
    }

    public function test_owner_dashboard_pages_render_creation_panels_and_barbers_only_create_for_themselves(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $barberUser = User::factory()->create();
        Membership::factory()->for($barberUser)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);
        $barber = Barber::factory()->for($barbershop)->for($barberUser, 'user')->create();
        $businessHour = BusinessHour::factory()->for($barbershop)->create([
            'weekday' => BusinessHour::WEEKDAY_MONDAY,
            'opens_at' => '09:00',
            'closes_at' => '18:00',
            'closed' => false,
        ]);
        BarberAvailability::factory()->for($barber)->create([
            'weekday' => BusinessHour::WEEKDAY_MONDAY,
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ]);

        $this->actingAs($owner)
            ->get(route('tenant.barbers.index', $barbershop, false))
            ->assertOk()
            ->assertSee('id="create-barber-invitation-title"', false)
            ->assertSee('method="POST" action="'.route('tenant.barbers.invitations.store', $barbershop).'"', false)
            ->assertSee('id="create-barber-profile-title"', false)
            ->assertSee('method="POST" action="'.route('tenant.barbers.store', $barbershop).'"', false)
            ->assertSee('id="barber-'.$barber->id.'-title"', false)
            ->assertSee('action="'.route('tenant.barbers.update', [$barbershop, $barber]).'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('form="barber-update-'.$barber->id.'"', false)
            ->assertSee('form="barber-invitation-create"', false)
            ->assertSee('form="barber-profile-create"', false);

        $this->get(route('tenant.business-hours.index', $barbershop, false))
            ->assertOk()
            ->assertSee('id="create-business-hour-title"', false)
            ->assertSee('method="POST" action="'.route('tenant.business-hours.store', $barbershop).'"', false)
            ->assertSee('id="business-hour-'.$businessHour->id.'-title"', false)
            ->assertSee('action="'.route('tenant.business-hours.update', [$barbershop, $businessHour]).'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('form="business-hour-update-'.$businessHour->id.'"', false)
            ->assertSee('form="business-hour-create"', false);

        $availability = $barber->availabilities->first();
        $this->get(route('tenant.barber-availability.index', $barbershop, false))
            ->assertOk()
            ->assertSee('id="create-availability-title"', false)
            ->assertSee('method="POST" action="'.route('tenant.barber-availability.store', $barbershop).'"', false)
            ->assertSee('id="availability-'.$availability->id.'-title"', false)
            ->assertSee('action="'.route('tenant.barber-availability.update', [$barbershop, $availability]).'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('form="availability-update-'.$availability->id.'"', false)
            ->assertSee('form="availability-create"', false);

        $this->get(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->assertOk()
            ->assertSee('id="create-appointment-title"', false)
            ->assertSee('method="POST" action="'.route('tenant.appointments.store', $barbershop).'"', false)
            ->assertSee('form="appointment-create"', false);

        $this->actingAs($owner)
            ->get(route('tenant.dashboard', $barbershop, false))
            ->assertOk()
            ->assertSee('Resumen del negocio')
            ->assertSee('max-w-7xl', false)
            ->assertSee(route('tenant.services.index', $barbershop), false);

        $admin = User::factory()->create();
        Membership::factory()->for($admin)->for($barbershop)->create(['role' => Membership::ROLE_ADMIN]);
        $this->actingAs($admin)
            ->get(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->assertOk()
            ->assertSee('id="create-appointment-title"', false);
        $this->get(route('tenant.business-hours.index', $barbershop, false))
            ->assertOk()
            ->assertSee('id="create-business-hour-title"', false);

        $this->actingAs($barberUser)
            ->get(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->assertOk()
            ->assertSee('id="create-appointment-title"', false)
            ->assertSee('Crear cita')
            ->assertDontSee('name="barber_id"', false)
            ->assertViewHas('canManageMembers', false);
    }

    public function test_invalid_business_hour_create_and_update_reopen_only_the_matching_panel(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $businessHour = BusinessHour::factory()->for($barbershop)->create([
            'weekday' => BusinessHour::WEEKDAY_MONDAY,
            'opens_at' => '09:00',
            'closes_at' => '18:00',
        ]);
        $otherBusinessHour = BusinessHour::factory()->for($barbershop)->create([
            'weekday' => BusinessHour::WEEKDAY_TUESDAY,
            'opens_at' => '08:00',
            'closes_at' => '16:00',
        ]);

        $this->actingAs($owner)
            ->from(route('tenant.business-hours.index', $barbershop, false))
            ->post(route('tenant.business-hours.store', $barbershop, false), [
                'form_context' => 'create-business-hour',
                'weekday' => BusinessHour::WEEKDAY_MONDAY,
                'opens_at' => '10:00',
                'closes_at' => '17:00',
                'closed' => false,
            ])
            ->assertRedirect(route('tenant.business-hours.index', $barbershop, false))
            ->assertSessionHasErrors('weekday');

        $createResponse = $this->get(route('tenant.business-hours.index', $barbershop, false));
        $createResponse->assertSee('value="10:00"', false)
            ->assertSee('value="09:00"', false);
        $this->assertOnlySlideOverOpen($createResponse, 'create-business-hour');
        $this->assertStringContainsString('value="10:00"', $this->formMarkup($createResponse, 'business-hour-create'));

        $editContext = 'business-hour-'.$businessHour->id;
        $this->from(route('tenant.business-hours.index', $barbershop, false))
            ->put(route('tenant.business-hours.update', [$barbershop, $businessHour], false), [
                'form_context' => $editContext,
                'weekday' => BusinessHour::WEEKDAY_MONDAY,
                'opens_at' => '19:00',
                'closes_at' => '08:00',
                'closed' => false,
            ])
            ->assertRedirect(route('tenant.business-hours.index', $barbershop, false))
            ->assertSessionHasErrors('opens_at');

        $editResponse = $this->get(route('tenant.business-hours.index', $barbershop, false));
        $editResponse->assertSee('action="'.route('tenant.business-hours.update', [$barbershop, $businessHour]).'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('form="business-hour-update-'.$businessHour->id.'"', false)
            ->assertSee('id="opens_at-'.$otherBusinessHour->id.'" name="opens_at" type="time" value="08:00"', false);
        $this->assertOnlySlideOverOpen($editResponse, $editContext);
        $this->assertStringContainsString('value="19:00"', $this->formMarkup($editResponse, 'business-hour-update-'.$businessHour->id));
    }

    public function test_invalid_availability_create_and_update_reopen_only_the_matching_panel(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $barber = Barber::factory()->for($barbershop)->create();
        BusinessHour::factory()->for($barbershop)->create([
            'weekday' => BusinessHour::WEEKDAY_MONDAY,
            'opens_at' => '09:00',
            'closes_at' => '18:00',
            'closed' => false,
        ]);
        BusinessHour::factory()->for($barbershop)->create([
            'weekday' => BusinessHour::WEEKDAY_TUESDAY,
            'opens_at' => '08:00',
            'closes_at' => '18:00',
            'closed' => false,
        ]);
        $availability = BarberAvailability::factory()->for($barber)->create([
            'weekday' => BusinessHour::WEEKDAY_MONDAY,
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ]);
        $otherAvailability = BarberAvailability::factory()->for($barber)->create([
            'weekday' => BusinessHour::WEEKDAY_TUESDAY,
            'starts_at' => '10:00',
            'ends_at' => '16:00',
        ]);

        $this->actingAs($owner)
            ->from(route('tenant.barber-availability.index', $barbershop, false))
            ->post(route('tenant.barber-availability.store', $barbershop, false), [
                'form_context' => 'create-availability',
                'barber_id' => $barber->id,
                'weekday' => BusinessHour::WEEKDAY_MONDAY,
                'starts_at' => '10:00',
                'ends_at' => '16:00',
            ])
            ->assertRedirect(route('tenant.barber-availability.index', $barbershop, false))
            ->assertSessionHasErrors('weekday');

        $createResponse = $this->get(route('tenant.barber-availability.index', $barbershop, false));
        $this->assertOnlySlideOverOpen($createResponse, 'create-availability');
        $this->assertStringContainsString('value="10:00"', $this->formMarkup($createResponse, 'availability-create'));
        $createResponse->assertSee('value="09:00"', false);

        $editContext = 'availability-'.$availability->id;
        $this->from(route('tenant.barber-availability.index', $barbershop, false))
            ->put(route('tenant.barber-availability.update', [$barbershop, $availability], false), [
                'form_context' => $editContext,
                'weekday' => BusinessHour::WEEKDAY_MONDAY,
                'starts_at' => '17:30',
                'ends_at' => '10:00',
                'active' => true,
            ])
            ->assertRedirect(route('tenant.barber-availability.index', $barbershop, false))
            ->assertSessionHasErrors('starts_at');

        $editResponse = $this->get(route('tenant.barber-availability.index', $barbershop, false));
        $editResponse->assertSee('action="'.route('tenant.barber-availability.update', [$barbershop, $availability]).'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('form="availability-update-'.$availability->id.'"', false)
            ->assertSee('id="starts-at-'.$otherAvailability->id.'" name="starts_at" type="time" value="10:00"', false);
        $this->assertOnlySlideOverOpen($editResponse, $editContext);
        $this->assertStringContainsString('value="17:30"', $this->formMarkup($editResponse, 'availability-update-'.$availability->id));
    }

    public function test_invalid_service_create_reopens_its_panel_without_replacing_existing_service_values(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $service = Service::factory()->for($barbershop)->create(['name' => 'Servicio vigente']);

        $this->actingAs($owner)
            ->from(route('tenant.services.index', $barbershop, false))
            ->post(route('tenant.services.store', $barbershop, false), [
                'form_context' => 'create-service',
                'name' => 'Servicio borrador',
                'description' => 'Descripción del borrador',
                'price' => '-1',
                'duration_minutes' => 30,
            ])
            ->assertRedirect(route('tenant.services.index', $barbershop, false))
            ->assertSessionHasErrors('price');

        $response = $this->get(route('tenant.services.index', $barbershop, false));
        $this->assertOnlySlideOverOpen($response, 'create-service');
        $createForm = $this->formMarkup($response, 'service-create');
        $this->assertStringContainsString('method="POST" action="'.route('tenant.services.store', $barbershop).'"', $createForm);
        $this->assertStringContainsString('value="Servicio borrador"', $createForm);
        $this->assertStringContainsString('value="-1"', $createForm);
        $response->assertSee('id="name-'.$service->id.'" name="name" type="text" value="Servicio vigente"', false)
            ->assertSee('form="service-create"', false);
    }

    public function test_invalid_barber_profile_create_reopens_its_panel_with_submitted_values(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $candidate = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        Membership::factory()->for($candidate)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);
        Barber::factory()->for($barbershop)->for($candidate, 'user')->create();

        $this->actingAs($owner)
            ->from(route('tenant.barbers.index', $barbershop, false))
            ->post(route('tenant.barbers.store', $barbershop, false), [
                'form_context' => 'create-barber-profile',
                'user_id' => $candidate->id,
                'display_name' => 'Perfil duplicado',
            ])
            ->assertRedirect(route('tenant.barbers.index', $barbershop, false))
            ->assertSessionHasErrors('user_id');

        $response = $this->get(route('tenant.barbers.index', $barbershop, false));
        $this->assertOnlySlideOverOpen($response, 'create-barber-profile');
        $profileForm = $this->formMarkup($response, 'barber-profile-create');
        $this->assertStringContainsString('value="Perfil duplicado"', $profileForm);
        $this->assertStringNotContainsString('Perfil duplicado', $this->formMarkup($response, 'barber-invitation-create'));
    }

    public function test_invalid_barber_profile_update_and_invitation_reopen_only_their_forms(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $barber = Barber::factory()->for($barbershop)->create(['display_name' => 'Nombre vigente']);
        $otherBarber = Barber::factory()->for($barbershop)->create(['display_name' => 'Otro perfil']);

        $editContext = 'barber-'.$barber->id;
        $this->actingAs($owner)
            ->from(route('tenant.barbers.index', $barbershop, false))
            ->put(route('tenant.barbers.update', [$barbershop, $barber], false), [
                'form_context' => $editContext,
                'display_name' => '',
                'active' => true,
            ])
            ->assertRedirect(route('tenant.barbers.index', $barbershop, false))
            ->assertSessionHasErrors('display_name');

        $editResponse = $this->get(route('tenant.barbers.index', $barbershop, false));
        $this->assertOnlySlideOverOpen($editResponse, $editContext);
        $editResponse->assertSee('action="'.route('tenant.barbers.update', [$barbershop, $barber]).'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('form="barber-update-'.$barber->id.'"', false)
            ->assertSee('id="display_name-'.$otherBarber->id.'" name="display_name" type="text" value="Otro perfil"', false);

        $this->from(route('tenant.barbers.index', $barbershop, false))
            ->post(route('tenant.barbers.invitations.store', $barbershop, false), [
                'form_context' => 'create-barber-invitation',
                'account_name' => 'Nueva persona',
                'account_email' => 'correo-no-valido',
                'agenda_name' => 'Nombre de agenda',
            ])
            ->assertRedirect(route('tenant.barbers.index', $barbershop, false))
            ->assertSessionHasErrors('account_email');

        $invitationResponse = $this->get(route('tenant.barbers.index', $barbershop, false));
        $this->assertOnlySlideOverOpen($invitationResponse, 'create-barber-invitation');
        $invitationForm = $this->formMarkup($invitationResponse, 'barber-invitation-create');
        $this->assertStringContainsString('value="Nueva persona"', $invitationForm);
        $this->assertStringContainsString('value="correo-no-valido"', $invitationForm);
        $this->assertStringNotContainsString('Nueva persona', $this->formMarkup($invitationResponse, 'barber-profile-create'));
    }

    public function test_invalid_appointment_creation_reopens_its_panel_and_preserves_role_boundary(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $date = '2026-10-05';
        $indexUrl = route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => $date], false);

        $this->actingAs($owner)
            ->from($indexUrl)
            ->post(route('tenant.appointments.store', $barbershop, false), [
                'form_context' => 'create-appointment',
                'barber_id' => 999999,
                'service_id' => 999999,
                'starts_at' => $date.'T11:00',
            ])
            ->assertRedirect($indexUrl)
            ->assertSessionHasErrors(['barber_id', 'service_id']);

        $response = $this->get($indexUrl);
        $this->assertOnlySlideOverOpen($response, 'create-appointment');
        $this->assertStringContainsString('value="'.$date.'T11:00"', $this->formMarkup($response, 'appointment-create'));
        $response->assertSee('action="'.route('tenant.appointments.store', $barbershop).'"', false)
            ->assertSee('form="appointment-create"', false);
    }

    private function assertOnlySlideOverOpen(TestResponse $response, string $expectedId): void
    {
        preg_match_all('/<div\\s+x-data="(.*?)"\\s+x-on:form-slide-over-open\\.window/s', $response->getContent(), $slideOvers);
        $openPanels = array_values(array_filter(
            $slideOvers[1],
            fn (string $panel): bool => str_contains($panel, 'isOpen: true'),
        ));

        $this->assertCount(1, $openPanels);
        $this->assertStringContainsString("window.bhActiveSlideOverId = '{$expectedId}';", $openPanels[0]);
    }

    private function formMarkup(TestResponse $response, string $formId): string
    {
        preg_match('/<form id="'.preg_quote($formId, '/').'".*?<\\/form>/s', $response->getContent(), $form);

        $this->assertNotEmpty($form);

        return $form[0];
    }
}
