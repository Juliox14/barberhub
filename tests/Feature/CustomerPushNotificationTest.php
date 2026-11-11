<?php

namespace Tests\Feature;

use App\Jobs\SendCustomerReminderPush;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\CustomerPreference;
use App\Models\PushSubscription;
use App\Models\Service;
use App\Models\User;
use App\Support\WebPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_customer_reminder_delivers_once_and_records_only_successful_period(): void
    {
        $this->travelTo('2026-10-05 12:00:00 UTC');
        $shop = Barbershop::factory()->create(['timezone' => 'America/Mexico_City']);
        $user = User::factory()->create();
        $customer = Customer::factory()->for($shop)->for($user)->create();
        $preference = CustomerPreference::factory()->for($customer)->create(['preferences' => [
            'notes' => 'No perder preferencias',
            'customer_reminders' => [
                'enabled' => true,
                'frequency_months' => 1,
                'activated_at' => '2026-08-01T10:00:00-06:00',
            ],
        ]]);
        $subscription = PushSubscription::factory()->for($user)->create();
        $sender = $this->mock(WebPushSender::class);
        $sender->shouldReceive('send')->times(3)->withArgs(fn (PushSubscription $sent, array $payload): bool => $sent->is($subscription)
            && $payload['title'] === '¿Listo para tu próximo corte?'
            && $payload['url'] === route('customer.profile.show', $shop, false)
        );

        (new SendCustomerReminderPush($customer->id, $shop->id))->handle($sender);
        (new SendCustomerReminderPush($customer->id, $shop->id))->handle($sender);
        $this->travelTo('2026-11-05 12:00:00 UTC');
        (new SendCustomerReminderPush($customer->id, $shop->id))->handle($sender);
        $this->travelTo('2026-12-05 12:00:00 UTC');
        (new SendCustomerReminderPush($customer->id, $shop->id))->handle($sender);

        $this->assertNotEmpty($preference->fresh()->preferences['customer_reminders']['last_sent_period']);
        $this->assertSame('No perder preferencias', $preference->fresh()->preferences['notes']);
    }

    public function test_fortnight_reminders_repeat_every_fourteen_days_without_a_haircut(): void
    {
        $this->travelTo('2026-10-05 12:00:00 UTC');
        $shop = Barbershop::factory()->create(['timezone' => 'UTC']);
        $user = User::factory()->create();
        $customer = Customer::factory()->for($shop)->for($user)->create();
        $preference = CustomerPreference::factory()->for($customer)->create(['preferences' => [
            'customer_reminders' => [
                'enabled' => true,
                'frequency_months' => 1,
                'frequency_weeks' => 2,
                'activated_at' => '2026-09-01T12:00:00Z',
            ],
        ]]);
        PushSubscription::factory()->for($user)->create();
        $sender = $this->mock(WebPushSender::class);
        $sender->shouldReceive('send')->times(3);

        (new SendCustomerReminderPush($customer->id, $shop->id))->handle($sender);
        $this->travelTo('2026-10-19 12:00:00 UTC');
        (new SendCustomerReminderPush($customer->id, $shop->id))->handle($sender);
        $this->travelTo('2026-11-02 12:00:00 UTC');
        (new SendCustomerReminderPush($customer->id, $shop->id))->handle($sender);

        $this->assertSame('2026-10-27T12:00:00Z', $preference->fresh()->preferences['customer_reminders']['last_sent_period']);
    }

    public function test_newer_completed_visit_resets_due_date_and_failed_delivery_does_not_consume_period(): void
    {
        $this->travelTo('2026-10-05 12:00:00 UTC');
        $shop = Barbershop::factory()->create();
        $user = User::factory()->create();
        $customer = Customer::factory()->for($shop)->for($user)->create();
        $barber = Barber::factory()->for($shop)->create();
        $service = Service::factory()->for($shop)->create();
        Appointment::factory()->for($shop)->for($barber)->for($service)->for($customer)->completed()->create([
            'starts_at' => '2026-10-01 10:00:00',
        ]);
        $preference = CustomerPreference::factory()->for($customer)->create(['preferences' => [
            'customer_reminders' => [
                'enabled' => true,
                'frequency_months' => 1,
                'activated_at' => '2026-07-01T10:00:00Z',
            ],
        ]]);
        PushSubscription::factory()->for($user)->create();
        $sender = $this->mock(WebPushSender::class);
        $sender->shouldNotReceive('send');

        (new SendCustomerReminderPush($customer->id, $shop->id))->handle($sender);
        $this->assertArrayNotHasKey('last_sent_period', $preference->fresh()->preferences['customer_reminders']);

        Appointment::query()->delete();
        $sender->shouldReceive('send')->once()->andThrow(new \RuntimeException('push failure'));
        try {
            (new SendCustomerReminderPush($customer->id, $shop->id))->handle($sender);
            $this->fail('The sender failure should propagate for queue retry.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('push failure', $exception->getMessage());
        }
        $this->assertArrayNotHasKey('last_sent_period', $preference->fresh()->preferences['customer_reminders']);
    }

    public function test_customer_cannot_use_the_existing_staff_subscription_route(): void
    {
        $shop = Barbershop::factory()->create();
        $user = User::factory()->create();
        Customer::factory()->for($shop)->for($user)->create();

        $this->actingAs($user)
            ->postJson(route('tenant.push-subscriptions.store', $shop, false), [])
            ->assertForbidden();
    }

    public function test_disabled_reminder_is_rechecked_before_delivery(): void
    {
        $shop = Barbershop::factory()->create();
        $user = User::factory()->create();
        $customer = Customer::factory()->for($shop)->for($user)->create();
        CustomerPreference::factory()->for($customer)->create(['preferences' => [
            'customer_reminders' => ['enabled' => false, 'frequency_months' => 1],
        ]]);
        PushSubscription::factory()->for($user)->create();
        $sender = $this->mock(WebPushSender::class);
        $sender->shouldNotReceive('send');

        (new SendCustomerReminderPush($customer->id, $shop->id))->handle($sender);
    }
}
