<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\CustomerPreference;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CustomerReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_enable_monthly_reminders_and_preserve_other_preferences(): void
    {
        $shop = Barbershop::factory()->create();
        $user = User::factory()->create();
        $customer = Customer::factory()->for($shop)->for($user)->create();
        CustomerPreference::factory()->for($customer)->create(['preferences' => [
            'notes' => 'Dejar volumen arriba.',
            'preferred_service_id' => 17,
            'future_setting' => ['enabled' => true],
        ]]);
        Queue::fake();
        config(['webpush.public_key' => 'public', 'webpush.private_key' => 'private', 'webpush.subject' => 'mailto:test@example.test']);
        PushSubscription::factory()->for($user)->create();

        $this->actingAs($user)->putJson(route('customer.profile.reminders.update', $shop), [
            'enabled' => true,
            'frequency_months' => 1,
        ])->assertOk()->assertJsonPath('enabled', true);

        $preferences = $customer->preference()->firstOrFail()->preferences;
        $this->assertSame('Dejar volumen arriba.', $preferences['notes']);
        $this->assertSame(17, $preferences['preferred_service_id']);
        $this->assertSame(['enabled' => true], $preferences['future_setting']);
        $this->assertTrue($preferences['customer_reminders']['enabled']);
        $this->assertSame(1, $preferences['customer_reminders']['frequency_months']);
        $this->assertArrayHasKey('activated_at', $preferences['customer_reminders']);
        Queue::assertNothingPushed();
    }

    public function test_customer_can_save_fortnight_reminders_without_changing_legacy_month_setting(): void
    {
        $shop = Barbershop::factory()->create();
        $user = User::factory()->create();
        $customer = Customer::factory()->for($shop)->for($user)->create();
        CustomerPreference::factory()->for($customer)->create(['preferences' => [
            'customer_reminders' => ['enabled' => false, 'frequency_months' => 3],
            'other_setting' => 'preserve',
        ]]);
        Queue::fake();
        config(['webpush.public_key' => 'public', 'webpush.private_key' => 'private', 'webpush.subject' => 'mailto:test@example.test']);
        PushSubscription::factory()->for($user)->create();

        $response = $this->actingAs($user)->putJson(route('customer.profile.reminders.update', $shop), [
            'enabled' => true,
            'frequency_weeks' => 2,
        ])->assertOk()->assertJsonPath('enabled', true)
            ->assertJsonPath('frequency_weeks', 2);

        $this->assertSame(3, $customer->fresh()->preference()->firstOrFail()->preferences['customer_reminders']['frequency_months']);
        $this->assertSame(2, $customer->fresh()->preference()->firstOrFail()->preferences['customer_reminders']['frequency_weeks']);

        $this->actingAs($user)->putJson(route('customer.profile.reminders.update', $shop), [
            'enabled' => false,
        ])->assertOk()->assertJsonPath('frequency_weeks', 2);

        $this->assertFalse($customer->fresh()->preference()->firstOrFail()->preferences['customer_reminders']['enabled']);
        $this->assertSame(3, $customer->fresh()->preference()->firstOrFail()->preferences['customer_reminders']['frequency_months']);
        $this->assertSame(2, $customer->fresh()->preference()->firstOrFail()->preferences['customer_reminders']['frequency_weeks']);
        $this->assertSame('preserve', $customer->fresh()->preference()->firstOrFail()->preferences['other_setting']);
    }

    public function test_customer_reminder_rejects_ambiguous_frequency_without_mutating_preferences(): void
    {
        $shop = Barbershop::factory()->create();
        $user = User::factory()->create();
        $customer = Customer::factory()->for($shop)->for($user)->create();
        CustomerPreference::factory()->for($customer)->create(['preferences' => [
            'customer_reminders' => ['enabled' => false, 'frequency_months' => 1],
        ]]);
        $before = $customer->preference()->firstOrFail()->preferences;

        $this->actingAs($user)->putJson(route('customer.profile.reminders.update', $shop), [
            'enabled' => true,
            'frequency_months' => 1,
            'frequency_weeks' => 2,
        ])->assertUnprocessable();

        $this->assertSame($before, $customer->fresh()->preference()->firstOrFail()->preferences);
    }

    public function test_customer_reminder_frequency_only_accepts_calendar_month_options(): void
    {
        $shop = Barbershop::factory()->create();
        $user = User::factory()->create();
        $customer = Customer::factory()->for($shop)->for($user)->create();
        CustomerPreference::factory()->for($customer)->create(['preferences' => ['notes' => 'original']]);
        $before = $customer->preference()->firstOrFail()->preferences;

        $this->actingAs($user)->putJson(route('customer.profile.reminders.update', $shop), [
            'enabled' => true,
            'frequency_months' => 4,
        ])->assertUnprocessable();

        $this->assertSame($before, $customer->fresh()->preference()->firstOrFail()->preferences);
    }

    public function test_customer_reminder_endpoints_require_customer_ownership(): void
    {
        $shop = Barbershop::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user)->putJson(route('customer.profile.reminders.update', $shop), [
            'enabled' => true,
            'frequency_months' => 1,
        ])->assertNotFound();
    }
}
