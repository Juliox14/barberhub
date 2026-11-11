<?php

namespace App\Console\Commands;

use App\Jobs\SendCustomerReminderPush;
use App\Models\Barbershop;
use App\Models\CustomerPreference;
use App\Support\CustomerReminders;
use Illuminate\Console\Command;

class DispatchCustomerReminders extends Command
{
    protected $signature = 'customers:dispatch-reminders';

    protected $description = 'Encola recordatorios push de clientes vencidos; requiere el scheduler de Laravel y un worker de cola activo.';

    public function handle(): int
    {
        CustomerPreference::query()
            ->where('preferences->customer_reminders->enabled', true)
            ->with('customer')
            ->chunkById(100, function ($preferences): void {
                foreach ($preferences as $preference) {
                    $customer = $preference->customer;
                    $barbershop = $customer?->barbershop;
                    if ($customer === null || $barbershop === null
                        || $barbershop->status !== Barbershop::STATUS_ACTIVE) {
                        continue;
                    }

                    $dueAt = CustomerReminders::dueAt($customer, $barbershop);
                    if ($dueAt !== null && $dueAt->isPast()) {
                        SendCustomerReminderPush::dispatch($customer->id, $barbershop->id);
                    }
                }
            });

        return self::SUCCESS;
    }
}
