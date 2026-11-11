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
        $frequencyWeeks = isset($settings['frequency_weeks']) ? (int) $settings['frequency_weeks'] : null;
        $baseline = $baseline->setTimezone($barbershop->timezone);
        $firstDueAt = self::advance($baseline, $frequencyMonths, $frequencyWeeks);
        $lastSentAt = isset($settings['last_sent_period'])
            ? CarbonImmutable::parse($settings['last_sent_period'])->setTimezone($barbershop->timezone)
            : null;

        if ($lastSentAt !== null && $lastSentAt->greaterThanOrEqualTo($baseline)) {
            return self::advance($lastSentAt, $frequencyMonths, $frequencyWeeks);
        }

        return $firstDueAt;
    }

    public static function latestDuePeriod(CarbonImmutable $dueAt, CarbonImmutable $now, int $frequencyMonths, ?int $frequencyWeeks = null): CarbonImmutable
    {
        $period = $dueAt;
        while ($period->lessThanOrEqualTo($now)) {
            $nextPeriod = self::advance($period, $frequencyMonths, $frequencyWeeks);
            if ($nextPeriod->greaterThan($now)) {
                return $period;
            }
            $period = $nextPeriod;
        }

        return $period;
    }

    private static function advance(CarbonImmutable $date, int $frequencyMonths, ?int $frequencyWeeks): CarbonImmutable
    {
        return $frequencyWeeks !== null
            ? $date->addWeeks($frequencyWeeks)
            : $date->addMonthsNoOverflow($frequencyMonths);
    }

    public static function periodKey(DateTimeInterface $dueAt): string
    {
        return CarbonImmutable::instance($dueAt)->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
