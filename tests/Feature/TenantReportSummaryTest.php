<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\ModuleContainment;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantReportSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_local_month_summary_all_statuses_filters_and_responsive_report_navigation(): void
    {
        $barbershop = Barbershop::factory()->create(['timezone' => 'America/Mexico_City']);
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $barber = Barber::factory()->for($barbershop)->create();
        $service = Service::factory()->for($barbershop)->create();
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00', $barbershop->timezone));

        foreach ([
            [Appointment::STATUS_COMPLETED, '2026-10-02 09:00:00'],
            [Appointment::STATUS_CANCELLED, '2026-10-05 09:00:00'],
            [Appointment::STATUS_NO_SHOW, '2026-10-08 09:00:00'],
            [Appointment::STATUS_SCHEDULED, '2026-10-12 09:00:00'],
        ] as [$status, $startsAt]) {
            Appointment::factory()->for($barbershop)->for($barber)->for($service)->create([
                'status' => $status,
                'starts_at' => $startsAt,
                'ends_at' => date('Y-m-d H:i:s', strtotime($startsAt.' +30 minutes')),
            ]);
        }
        Appointment::factory()->for($barbershop)->for($barber)->for($service)->create([
            'status' => Appointment::STATUS_SCHEDULED,
            'starts_at' => '2026-10-01 00:00:00',
            'ends_at' => '2026-10-01 00:30:00',
        ]);
        Appointment::factory()->for($barbershop)->for($barber)->for($service)->create([
            'status' => Appointment::STATUS_SCHEDULED,
            'starts_at' => '2026-10-15 23:59:59',
            'ends_at' => '2026-10-16 00:29:59',
        ]);
        Appointment::factory()->for($barbershop)->for($barber)->for($service)->create([
            'starts_at' => '2026-10-16 09:00:00',
            'ends_at' => '2026-10-16 09:30:00',
        ]);
        Appointment::factory()->for($barbershop)->for($barber)->for($service)->create([
            'starts_at' => '2026-09-30 09:00:00',
            'ends_at' => '2026-09-30 09:30:00',
        ]);

        $response = $this->actingAs($owner)->get(route('tenant.reports.summary', $barbershop, false));

        $response->assertOk()
            ->assertViewHas('summary', fn (array $summary): bool => $summary === [
                'total' => 6,
                'completed' => 1,
                'cancelled' => 1,
                'no_show' => 1,
                'scheduled' => 3,
            ])
            ->assertSee('Resumen de citas')
            ->assertSee('Del 01/10/2026 al 15/10/2026')
            ->assertSee('America/Mexico_City')
            ->assertSee('name="from" type="date" value="2026-10-01"', false)
            ->assertSee('name="to" type="date" value="2026-10-15"', false)
            ->assertSee('Total de citas')
            ->assertSee('Completadas')
            ->assertSee('Canceladas')
            ->assertSee('No asistió')
            ->assertSee('Programadas')
            ->assertSee('Reportes')
            ->assertSee(route('tenant.reports.summary', $barbershop), false)
            ->assertSee(route('tenant.reports.evolution', $barbershop), false)
            ->assertSee(route('tenant.reports.demand', $barbershop), false)
            ->assertSee(route('tenant.reports.services', $barbershop), false)
            ->assertSee(route('tenant.reports.barbers', $barbershop), false)
            ->assertSee(route('tenant.reports.customers', $barbershop), false)
            ->assertSee('data-report-navigation="desktop mobile"', false)
            ->assertSee('fixed inset-y-0', false)
            ->assertSee('lg:sticky', false)
            ->assertSee(route('tenant.appointments.index', $barbershop), false);
    }

    public function test_report_filters_include_inactive_tenant_records_and_exclude_other_appointments(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_ADMIN]);
        $inactiveBarber = Barber::factory()->for($barbershop)->create(['active' => false]);
        $inactiveService = Service::factory()->for($barbershop)->create(['active' => false]);
        $otherBarber = Barber::factory()->for($barbershop)->create();
        $otherService = Service::factory()->for($barbershop)->create();
        Appointment::factory()->for($barbershop)->for($inactiveBarber)->for($inactiveService)->completed()->create([
            'starts_at' => '2026-10-10 11:00:00',
            'ends_at' => '2026-10-10 11:30:00',
        ]);
        Appointment::factory()->for($barbershop)->for($otherBarber)->for($otherService)->cancelled()->create([
            'starts_at' => '2026-10-10 12:00:00',
            'ends_at' => '2026-10-10 12:30:00',
        ]);

        $this->actingAs($owner)
            ->get(route('tenant.reports.summary', [
                'barbershop' => $barbershop,
                'from' => '2026-10-01',
                'to' => '2026-10-31',
                'barber_id' => $inactiveBarber->id,
                'service_id' => $inactiveService->id,
            ], false))
            ->assertOk()
            ->assertViewHas('summary', fn (array $summary): bool => $summary === [
                'total' => 1,
                'completed' => 1,
                'cancelled' => 0,
                'no_show' => 0,
                'scheduled' => 0,
            ])
            ->assertSee('name="barber_id"', false)
            ->assertSee('name="service_id"', false)
            ->assertSee('value="'.$inactiveBarber->id.'" selected', false)
            ->assertSee('value="'.$inactiveService->id.'" selected', false)
            ->assertSee(' (inactivo)');
    }

    public function test_report_shows_empty_state_when_the_period_has_no_appointments(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);

        $this->actingAs($owner)
            ->get(route('tenant.reports.summary', $barbershop, false))
            ->assertOk()
            ->assertSee('No hay citas en este periodo')
            ->assertSee('Ajusta el periodo o los filtros');
    }

    public function test_only_owner_admin_and_platform_admin_can_view_tenant_report(): void
    {
        $barbershop = Barbershop::factory()->create();
        $barber = User::factory()->create();
        Membership::factory()->for($barber)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);
        $stranger = User::factory()->create();
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

        $this->get(route('tenant.reports.summary', $barbershop, false))
            ->assertRedirect(route('login', absolute: false));

        $this->actingAs($barber)
            ->get(route('tenant.reports.summary', $barbershop, false))
            ->assertForbidden();

        $this->get(route('tenant.dashboard', $barbershop, false))
            ->assertOk()
            ->assertDontSee('data-report-navigation="desktop mobile"', false);

        $this->actingAs($stranger)
            ->get(route('tenant.reports.summary', $barbershop, false))
            ->assertForbidden();

        $this->actingAs($platformAdmin)
            ->get(route('tenant.reports.summary', $barbershop, false))
            ->assertOk()
            ->assertSee('Reportes');
    }

    public function test_report_requires_appointments_module_to_be_available(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        ModuleContainment::query()->create([
            'barbershop_id' => $barbershop->id,
            'module' => 'appointments',
            'reason' => 'Test containment',
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->actingAs($owner)
            ->get(route('tenant.reports.summary', $barbershop, false))
            ->assertForbidden();
    }

    public function test_report_rejects_a_reversed_period(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);

        $this->actingAs($owner)
            ->get(route('tenant.reports.summary', [
                'barbershop' => $barbershop,
                'from' => '2026-10-02',
                'to' => '2026-10-01',
            ], false))
            ->assertRedirect()
            ->assertSessionHasErrors('to');
    }

    public function test_report_rejects_a_period_longer_than_366_days(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);

        $this->actingAs($owner)
            ->get(route('tenant.reports.summary', [
                'barbershop' => $barbershop,
                'from' => '2025-10-01',
                'to' => '2026-10-02',
            ], false))
            ->assertRedirect()
            ->assertSessionHasErrors('to');
    }

    public function test_report_accepts_a_366_day_period(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);

        $this->actingAs($owner)
            ->get(route('tenant.reports.summary', [
                'barbershop' => $barbershop,
                'from' => '2025-10-01',
                'to' => '2026-10-01',
            ], false))
            ->assertOk()
            ->assertSee('El periodo puede incluir hasta 366 días.');
    }

    public function test_report_rejects_invalid_calendar_dates(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);

        $this->actingAs($owner)
            ->get(route('tenant.reports.summary', [
                'barbershop' => $barbershop,
                'from' => '2026-13-01',
                'to' => '2026-10-02',
            ], false))
            ->assertRedirect()
            ->assertSessionHasErrors('from');
    }

    public function test_report_rejects_filter_ids_from_another_barbershop(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $otherShop = Barbershop::factory()->create();
        $foreignBarber = Barber::factory()->for($otherShop)->create();
        $foreignService = Service::factory()->for($otherShop)->create();

        $this->actingAs($owner)
            ->get(route('tenant.reports.summary', [
                'barbershop' => $barbershop,
                'from' => '2026-10-01',
                'to' => '2026-10-31',
                'barber_id' => $foreignBarber->id,
                'service_id' => $foreignService->id,
            ], false))
            ->assertRedirect()
            ->assertSessionHasErrors(['barber_id', 'service_id']);
    }
}
