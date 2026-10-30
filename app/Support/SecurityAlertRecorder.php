<?php

namespace App\Support;

use App\Notifications\SecurityIncidentAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class SecurityAlertRecorder
{
    public function record(string $event, ?int $subjectId, ?int $barbershopId, ?string $module, string $reason): void
    {
        $recipient = config('security.alert_recipient');
        $recipient = is_string($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL) ? $recipient : null;
        $fingerprint = hash('sha256', json_encode([$event, $subjectId, $barbershopId, $module], JSON_THROW_ON_ERROR));

        try {
            $sendEmail = DB::transaction(function () use ($fingerprint, $event, $subjectId, $barbershopId, $module, $reason, $recipient): bool {
                DB::table('security_alerts')->insertOrIgnore([
                    'fingerprint' => $fingerprint,
                    'event' => $event,
                    'subject_id' => $subjectId,
                    'barbershop_id' => $barbershopId,
                    'module' => $module,
                    'reason' => $reason,
                    'occurrences' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $alert = DB::table('security_alerts')->where('fingerprint', $fingerprint)->lockForUpdate()->firstOrFail();
                $sendEmail = $recipient !== null && ($alert->next_email_at === null || now()->gte(Carbon::parse($alert->next_email_at)));

                DB::table('security_alerts')->where('id', $alert->id)->update([
                    'occurrences' => $alert->occurrences + 1,
                    'reason' => $reason,
                    'updated_at' => now(),
                    'next_email_at' => $sendEmail ? now()->addMinutes(15) : $alert->next_email_at,
                ]);

                return $sendEmail;
            });
        } catch (Throwable) {
            Log::error('Security alert persistence failed.', ['event' => $event]);

            return;
        }

        if ($sendEmail) {
            try {
                $context = $subjectId !== null ? 'Cuenta #'.$subjectId : 'Barbería #'.$barbershopId.' · '.$module;
                Notification::route('mail', $recipient)->notify(new SecurityIncidentAlert($event, $context, $reason));
            } catch (Throwable) {
                Log::warning('Security alert email dispatch failed.', ['event' => $event]);
            }
        }
    }
}
