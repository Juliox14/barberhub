<?php

namespace App\Jobs;

use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\CustomerPreference;
use App\Models\PushSubscription;
use App\Support\CustomerReminders;
use App\Support\WebPushSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendCustomerReminderPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public int $customerId, public int $barbershopId) {}

    public function handle(WebPushSender $sender): void
    {
        DB::transaction(function () use ($sender): void {
            $customer = Customer::query()->whereKey($this->customerId)->lockForUpdate()->with('user')->first();
            $barbershop = Barbershop::query()->find($this->barbershopId);
            if ($customer === null || $barbershop === null
                || $customer->barbershop_id !== $barbershop->id
                || $customer->user === null
                || ! $customer->user->hasVerifiedEmail()
                || $barbershop->status !== Barbershop::STATUS_ACTIVE) {
                return;
            }

            $preference = CustomerPreference::query()->where('customer_id', $customer->id)->lockForUpdate()->first();
            $settings = $preference?->preferences['customer_reminders'] ?? [];
            $dueAt = CustomerReminders::dueAt($customer, $barbershop);
            if ($preference === null || ! ($settings['enabled'] ?? false) || $dueAt === null || $dueAt->isFuture()) {
                return;
            }

            $frequencyMonths = (int) ($settings['frequency_months'] ?? 1);
            $frequencyWeeks = isset($settings['frequency_weeks']) ? (int) $settings['frequency_weeks'] : null;
            $period = CustomerReminders::periodKey(CustomerReminders::latestDuePeriod(
                $dueAt,
                now($barbershop->timezone)->toImmutable(),
                $frequencyMonths,
                $frequencyWeeks,
            ));
            if (($settings['last_sent_period'] ?? null) === $period) {
                return;
            }

            $subscriptions = PushSubscription::query()
                ->where('user_id', $customer->user_id)
                ->where('session_hash', '!=', '')
                ->get();
            if ($subscriptions->isEmpty()) {
                return;
            }

            foreach ($subscriptions as $subscription) {
                $sender->send($subscription, [
                    'title' => '¿Listo para tu próximo corte?',
                    'body' => 'Ha llegado el momento de reservar tu próxima visita.',
                    'url' => route('customer.profile.show', $barbershop, false),
                ]);
            }

            $preferences = $preference->preferences ?? [];
            $preferences['customer_reminders']['last_sent_period'] = $period;
            $preference->preferences = $preferences;
            $preference->save();
        });
    }
}
