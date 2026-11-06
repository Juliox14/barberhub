<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\ModuleContainment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantOperationalReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_evolution_reports_zero_filled_daily_counts_and_comparison_for_local_period(): void
    {
        $barbershop = Barbershop::factory()->create(['timezone' => 'America/Mexico_City']);
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $barber = Barber::factory()->for($barbershop)->create();
        $service = Service::factory()->for($barbershop)->create();
        Appointment::factory()->for($barbershop)->for($barber)->for($service)->completed()->create([
            'starts_at' => '2026-10-01 00:15:00',
            'ends_at' => '2026-10-01 00:45:00',
        ]);
        Appointment::factory()->for($barbershop)->for($barber)->for($service)->completed()->create([
            'starts_at' => '2026-10-02 23:45:00',
            'ends_at' => '2026-10-03 00:15:00',
        ]);
        Appointment::factory()->for($barbershop)->for($barber)->for($service)->create([
            'status' => Appointment::STATUS_SCHEDULED,
            'starts_at' => '2026-10-02 09:00:00',
            'ends_at' => '2026-10-02 09:30:00',
        ]);
        Appointment::factory()->for($barbershop)->for($barber)->for($service)->completed()->create([
            'starts_at' => '2026-09-29 10:00:00',
            'ends_at' => '2026-09-29 10:30:00',
        ]);

        $this->actingAs($owner)
            ->get(route('tenant.reports.evolution', [
                'barbershop' => $barbershop,
                'from' => '2026-10-01',
                'to' => '2026-10-03',
            ], false))
            ->assertOk()
            ->assertViewHas('evolution', fn (array $evolution): bool => $evolution === [
                ['label' => '2026-10-01', 'count' => 1],
                ['label' => '2026-10-02', 'count' => 1],
                ['label' => '2026-10-03', 'count' => 0],
            ])
            ->assertViewHas('comparison', fn (array $comparison): bool => $comparison === [
                'previousFrom' => '2026-09-28',
                'previousTo' => '2026-09-30',
                'previousTotal' => 1,
                'currentTotal' => 2,
                'difference' => 1,
                'percentageDifference' => 100.0,
            ])
            ->assertSee('Evolución de atenciones')
            ->assertSee('2026-10-03')
            ->assertSee('Del 28/09/2026 al 30/09/2026');
    }

    public function test_evolution_accepts_week_and_month_groupings_and_handles_zero_comparison_base(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_ADMIN]);
        $barber = Barber::factory()->for($barbershop)->create();
        $service = Service::factory()->for($barbershop)->create();
        Appointment::factory()->for($barbershop)->for($barber)->for($service)->completed()->create([
            'starts_at' => '2026-10-05 08:00:00',
            'ends_at' => '2026-10-05 08:30:00',
        ]);

        $this->actingAs($owner)
            ->get(route('tenant.reports.evolution', [
                'barbershop' => $barbershop,
                'from' => '2026-10-01',
                'to' => '2026-10-31',
                'grouping' => 'week',
            ], false))
            ->assertOk()
            ->assertViewHas('grouping', 'week')
            ->assertSee('Semana');

        $this->get(route('tenant.reports.evolution', [
            'barbershop' => $barbershop,
            'from' => '2026-10-01',
            'to' => '2026-10-31',
            'grouping' => 'month',
        ], false))
            ->assertOk()
            ->assertViewHas('grouping', 'month')
            ->assertViewHas('comparison', fn (array $comparison): bool => $comparison['previousTotal'] === 0
                && $comparison['percentageDifference'] === null)
            ->assertSee('sin base de comparación');
    }

    public function test_demand_includes_all_booking_statuses_in_local_weekday_hour_buckets_and_applies_filters(): void
    {
        $barbershop = Barbershop::factory()->create(['timezone' => 'America/Mexico_City']);
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $barber = Barber::factory()->for($barbershop)->create();
        $otherBarber = Barber::factory()->for($barbershop)->create();
        $service = Service::factory()->for($barbershop)->create();
        $otherService = Service::factory()->for($barbershop)->create();
        foreach ([Appointment::STATUS_COMPLETED, Appointment::STATUS_SCHEDULED, Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW] as $status) {
            Appointment::factory()->for($barbershop)->for($barber)->for($service)->create([
                'status' => $status,
                'starts_at' => '2026-10-05 23:30:00',
                'ends_at' => '2026-10-06 00:00:00',
            ]);
        }
        Appointment::factory()->for($barbershop)->for($otherBarber)->for($otherService)->create([
            'starts_at' => '2026-10-05 23:00:00',
            'ends_at' => '2026-10-05 23:30:00',
        ]);
        Appointment::factory()->for($barbershop)->for($barber)->for($service)->create([
            'starts_at' => '2026-10-06 00:00:00',
            'ends_at' => '2026-10-06 00:30:00',
        ]);

        $this->actingAs($owner)
            ->get(route('tenant.reports.demand', [
                'barbershop' => $barbershop,
                'from' => '2026-10-05',
                'to' => '2026-10-05',
                'barber_id' => $barber->id,
                'service_id' => $service->id,
            ], false))
            ->assertOk()
            ->assertViewHas('demandTotal', 4)
            ->assertViewHas('weekdayCounts', fn (array $counts): bool => $counts[1] === 4)
            ->assertViewHas('hourCounts', fn (array $counts): bool => $counts[23] === 4 && $counts[0] === 0)
            ->assertSee('Demanda por día y horario')
            ->assertSee('Reservas (todos los estados)')
            ->assertSee('no representa demanda no atendida');
    }

    public function test_operational_reports_enforce_report_roles_and_appointments_module_gate(): void
    {
        $barbershop = Barbershop::factory()->create();
        $barber = User::factory()->create();
        Membership::factory()->for($barber)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);
        $url = route('tenant.reports.demand', $barbershop, false);

        $this->get($url)->assertRedirect(route('login', absolute: false));
        $this->actingAs($barber)->get($url)->assertForbidden();

        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        ModuleContainment::query()->create([
            'barbershop_id' => $barbershop->id,
            'module' => 'appointments',
            'reason' => 'Test containment',
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->actingAs($owner)->get($url)->assertForbidden();
    }
}
