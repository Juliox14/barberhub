<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAvailable;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberAvailability;
use App\Models\Barbershop;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModuleContainmentMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string, array{string, string, string}> */
    public static function protectedEndpoints(): iterable
    {
        $modules = [
            'members' => ['members.index' => 'GET', 'members.store' => 'POST', 'members.update' => 'PUT', 'members.destroy' => 'DELETE'],
            'barbers' => ['barbers.index' => 'GET', 'barbers.store' => 'POST', 'barbers.invitations.store' => 'POST', 'barbers.update' => 'PUT'],
            'services' => ['services.index' => 'GET', 'services.store' => 'POST', 'services.update' => 'PUT'],
            'appointments' => ['appointments.index' => 'GET', 'appointments.store' => 'POST', 'appointments.reschedule' => 'PUT', 'appointments.cancel' => 'POST', 'appointments.complete' => 'POST', 'appointments.no-show' => 'POST', 'customer-invitations.store' => 'POST'],
            'business-hours' => ['business-hours.index' => 'GET', 'business-hours.store' => 'POST', 'business-hours.update' => 'PUT'],
            'barber-availability' => ['barber-availability.index' => 'GET', 'barber-availability.store' => 'POST', 'barber-availability.update' => 'PUT'],
        ];

        foreach ($modules as $module => $routes) {
            foreach ($routes as $name => $method) {
                yield $method.' '.$name => [$module, 'tenant.'.$name, $method];
                if ($method === 'GET') {
                    yield 'HEAD '.$name => [$module, 'tenant.'.$name, 'HEAD'];
                }
            }
        }
    }

    #[DataProvider('protectedEndpoints')]
    public function test_every_route_rejects_without_effects_and_restores_its_normal_behavior(string $module, string $name, string $method): void
    {
        $this->travelTo('2026-10-28 12:00:00');
        Notification::fake();
        Http::preventStrayRequests();
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $shop = Barbershop::factory()->create();
        $otherShop = Barbershop::factory()->create();
        Membership::factory()->for($owner)->for($shop)->create(['role' => Membership::ROLE_OWNER]);
        Membership::factory()->for($owner)->for($otherShop)->create(['role' => Membership::ROLE_OWNER]);
        $url = $this->endpoint($name, $this->fixtures($shop));
        $otherUrl = $this->endpoint($name, $this->fixtures($otherShop));
        $this->actingAs($owner);
        $normalStatus = $this->normalRequest($name, $method, $url);
        $otherStatus = $this->normalRequest($name, $method, $otherUrl);

        $this->actingAs($admin)->post(route('platform.security.modules.suspend', $shop, false), [
            'module' => $module, 'reason' => 'Matriz HTTP aislada.',
        ])->assertRedirect();
        Notification::fake();
        $before = $this->protectedState();

        foreach ([$owner, $admin] as $actor) {
            $this->actingAs($actor)->json($method, $url)->assertForbidden();
            $this->assertSame($before, $this->protectedState(), $method.' '.$name.' changed persisted state after rejection');
            Notification::assertNothingSent();
        }

        $this->actingAs($owner);
        $this->assertSame($otherStatus, $this->normalRequest($name, $method, $otherUrl));
        $unaffectedRoute = $module === 'services' ? 'tenant.appointments.index' : 'tenant.services.index';
        $this->get(route($unaffectedRoute, $shop, false))->assertOk();
        $this->get(route('tenant.dashboard', $shop, false))->assertOk();
        $this->get(route('profile.edit', absolute: false))->assertOk();
        $this->actingAs($admin)->get(route('platform.security.index', absolute: false))->assertOk();

        $this->travel(15)->minutes();
        $this->actingAs($owner);
        $this->assertSame($normalStatus, $this->normalRequest($name, $method, $url));
        $this->actingAs($admin)->post(route('platform.security.modules.suspend', $shop, false), [
            'module' => $module, 'reason' => 'Comprobar levantamiento anticipado.',
        ])->assertRedirect();
        $this->actingAs($owner)->json($method, $url)->assertForbidden();
        $this->actingAs($admin)->post(route('platform.security.modules.release', $shop, false), [
            'module' => $module, 'reason' => 'Prueba finalizada.',
        ])->assertRedirect();
        $this->actingAs($owner);
        $this->assertSame($normalStatus, $this->normalRequest($name, $method, $url));
    }

    public function test_matrix_covers_every_registered_protected_method(): void
    {
        $expected = [];
        foreach (self::protectedEndpoints() as [$module, $name, $method]) {
            $expected[] = $module.' '.$method.' '.$name;
        }
        $actual = [];
        foreach (app('router')->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, EnsureModuleAvailable::class.':')) {
                    $module = substr($middleware, strlen(EnsureModuleAvailable::class) + 1);
                    foreach ($route->methods() as $method) {
                        $actual[] = $module.' '.$method.' '.$route->getName();
                    }
                }
            }
        }
        sort($expected);
        sort($actual);

        $this->assertCount(30, $actual);
        $this->assertSame($expected, $actual);
    }

    public function test_valid_writes_succeed_after_expiry_and_early_release(): void
    {
        Notification::fake();
        $this->travelTo('2026-10-28 12:00:00');
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $shop = Barbershop::factory()->create();
        $this->actingAs($admin);
        $suspend = route('platform.security.modules.suspend', $shop, false);
        $store = route('tenant.services.store', $shop, false);
        $payload = ['name' => 'Restaurado por vencimiento', 'price' => '15.00', 'duration_minutes' => 30];
        $this->post($suspend, ['module' => 'services', 'reason' => 'Prueba de escritura.'])->assertRedirect();
        $this->post($store, $payload)->assertForbidden();
        $this->assertDatabaseMissing('services', ['name' => $payload['name']]);

        $this->travel(15)->minutes();
        $this->post($store, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('services', ['barbershop_id' => $shop->id, 'name' => $payload['name'], 'price' => 15, 'duration_minutes' => 30]);
        $this->post($suspend, ['module' => 'services', 'reason' => 'Segunda prueba.'])->assertRedirect();
        $payload['name'] = 'Restaurado por levantamiento';
        $this->post($store, $payload)->assertForbidden();
        $this->assertDatabaseMissing('services', ['name' => $payload['name']]);
        $this->post(route('platform.security.modules.release', $shop, false), ['module' => 'services', 'reason' => 'Acceso restaurado.'])->assertRedirect();
        $this->post($store, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('services', ['barbershop_id' => $shop->id, 'name' => $payload['name']]);
        $this->assertDatabaseCount('services', 2);
    }

    /** @return array<string, Model> */
    private function fixtures(Barbershop $shop): array
    {
        $barber = Barber::factory()->for($shop)->create();
        $service = Service::factory()->for($shop)->create();
        $customer = Customer::factory()->for($shop)->create();

        return [
            'barbershop' => $shop,
            'membership' => Membership::factory()->for($shop)->create(['role' => Membership::ROLE_BARBER]),
            'barber' => $barber,
            'service' => $service,
            'customer' => $customer,
            'appointment' => Appointment::factory()->for($shop)->for($barber)->for($service)->for($customer)->create(),
            'businessHour' => BusinessHour::factory()->for($shop)->create(),
            'barberAvailability' => BarberAvailability::factory()->for($barber)->create(),
        ];
    }

    /** @param array<string, Model> $fixtures */
    private function endpoint(string $name, array $fixtures): string
    {
        $route = app('router')->getRoutes()->getByName($name);
        $parameters = array_intersect_key($fixtures, array_flip($route->parameterNames()));

        return route($name, $parameters, false);
    }

    private function normalRequest(string $name, string $method, string $url): int
    {
        DB::beginTransaction();
        try {
            $before = $this->protectedState();
            $response = $this->json($method, $url);
            $status = $response->getStatusCode();
            $this->assertContains($status, in_array($method, ['GET', 'HEAD'], true) ? [200] : [302, 422]);
            if (in_array($method, ['GET', 'HEAD'], true)) {
                $response->assertOk();
            }
            if (in_array($name, ['tenant.members.destroy', 'tenant.appointments.cancel', 'tenant.appointments.complete', 'tenant.appointments.no-show'], true)) {
                $response->assertRedirect();
                $this->assertNotSame($before, $this->protectedState(), $name.' must exercise the normal mutation');
            }

            return $status;
        } finally {
            DB::rollBack();
        }
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function protectedState(): array
    {
        $state = [];
        foreach (['users', 'barbershops', 'memberships', 'barbers', 'services', 'customers', 'appointments', 'business_hours', 'barber_availabilities', 'customer_invitations', 'push_subscriptions', 'password_reset_tokens', 'module_containments', 'security_audit_logs', 'security_alerts', 'jobs'] as $table) {
            $key = $table === 'password_reset_tokens' ? 'email' : 'id';
            $state[$table] = DB::table($table)->orderBy($key)->get()->map(fn (object $row): array => (array) $row)->all();
        }

        return $state;
    }
}
