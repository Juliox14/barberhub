<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\Customer;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class CustomerReminders
{
    /** @return array<string, mixed> */
    public static function settings(Customer $customer): array
    {
        return $customer->preference?->preferences['customer_reminders'] ?? [
            'enabled' => false,
            'frequency_months' => 1,
        ];
    }

    public static function dueAt(Customer $customer, Barbershop $barbershop): ?CarbonImmutable
    {
        $settings = self::settings($customer);
        if (! ($settings['enabled'] ?? false)) {
            return null;
        }

        $completedAt = Appointment::query()
            ->where('customer_id', $customer->id)
            ->where('barbershop_id', $barbershop->id)
            ->where('status', Appointment::STATUS_COMPLETED)
            ->max('starts_at');
        $baseline = $completedAt !== null
            ? CarbonImmutable::parse($completedAt, 'UTC')
            : (isset($settings['activated_at']) ? CarbonImmutable::parse($settings['activated_at']) : null);
        if ($baseline === null) {
            return null;
        }

        $frequencyMonths = (int) ($settings['frequency_months'] ?? 1);
        $baseline = $baseline->setTimezone($barbershop->timezone);
        $firstDueAt = $baseline->addMonthsNoOverflow($frequencyMonths);
        $lastSentAt = isset($settings['last_sent_period'])
            ? CarbonImmutable::parse($settings['last_sent_period'])->setTimezone($barbershop->timezone)
            : null;

        if ($lastSentAt !== null && $lastSentAt->greaterThanOrEqualTo($baseline)) {
            return $lastSentAt->addMonthsNoOverflow($frequencyMonths);
        }

        return $firstDueAt;
    }

    public static function latestDuePeriod(CarbonImmutable $dueAt, CarbonImmutable $now, int $frequencyMonths): CarbonImmutable
    {
        $period = $dueAt;
        while ($period->lessThanOrEqualTo($now)) {
            $nextPeriod = $period->addMonthsNoOverflow($frequencyMonths);
            if ($nextPeriod->greaterThan($now)) {
                return $period;
            }
            $period = $nextPeriod;
        }

        return $period;
    }

    public static function periodKey(DateTimeInterface $dueAt): string
    {
        return CarbonImmutable::instance($dueAt)->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
