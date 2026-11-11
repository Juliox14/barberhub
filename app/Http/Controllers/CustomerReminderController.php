<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCustomerRemindersRequest;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\CustomerPreference;
use App\Models\PushSubscription;
use App\Support\CustomerReminders;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CustomerReminderController extends Controller
{
    public function update(UpdateCustomerRemindersRequest $request, Barbershop $barbershop): JsonResponse
    {
        $customer = Customer::query()
            ->where('barbershop_id', $barbershop->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
        $validated = $request->validated();
        if ((bool) $validated['enabled']) {
            abort_unless($barbershop->status === Barbershop::STATUS_ACTIVE, 403);
            if (! config('webpush.public_key') || ! config('webpush.private_key') || ! config('webpush.subject')) {
                return response()->json(['message' => 'Las notificaciones todavía no están configuradas.'], 422);
            }
            abort_unless(PushSubscription::query()->where('user_id', $request->user()->id)->exists(), 422);
        }

        $preference = DB::transaction(function () use ($customer, $barbershop, $validated): CustomerPreference {
            Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $preference = CustomerPreference::query()->where('customer_id', $customer->id)->lockForUpdate()->first();
            if ($preference === null) {
                $preference = CustomerPreference::query()->create([
                    'customer_id' => $customer->id,
                    'preferences' => [],
                ]);
            }

            $preferences = $preference->preferences ?? [];
            $current = $preferences['customer_reminders'] ?? [];
            $enabled = (bool) $validated['enabled'];
            $reminders = [
                ...$current,
                'enabled' => $enabled,
                'frequency_months' => $enabled && isset($validated['frequency_months'])
                    ? (int) $validated['frequency_months']
                    : (int) ($current['frequency_months'] ?? 1),
            ];
            if ($enabled && isset($validated['frequency_weeks'])) {
                $reminders['frequency_weeks'] = (int) $validated['frequency_weeks'];
            } elseif ($enabled) {
                unset($reminders['frequency_weeks']);
            }
            if ($enabled && ! ($current['enabled'] ?? false)) {
                $reminders['activated_at'] = now($barbershop->timezone)->toIso8601String();
                unset($reminders['last_sent_period']);
            }
            $preferences['customer_reminders'] = $reminders;
            $preference->preferences = $preferences;
            $preference->save();

            return $preference;
        });

        $settings = CustomerReminders::settings($customer->fresh());

        return response()->json([
            'enabled' => (bool) ($settings['enabled'] ?? false),
            'frequency_months' => (int) ($settings['frequency_months'] ?? 1),
            'frequency_weeks' => isset($settings['frequency_weeks']) ? (int) $settings['frequency_weeks'] : null,
            'due_at' => CustomerReminders::dueAt($customer->fresh(), $barbershop)?->toIso8601String(),
        ]);
    }
}
