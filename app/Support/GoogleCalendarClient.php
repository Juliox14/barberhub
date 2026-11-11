<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class GoogleCalendarClient
{
    /**
     * @return array<string, mixed>|null
     */
    public function exchangeCode(string $code, string $redirectUri): ?array
    {
        try {
            $response = Http::asForm()
                ->connectTimeout(3)
                ->timeout(10)
                ->post('https://oauth2.googleapis.com/token', [
                    'code' => $code,
                    'client_id' => config('services.google_calendar.client_id'),
                    'client_secret' => config('services.google_calendar.client_secret'),
                    'redirect_uri' => $redirectUri,
                    'grant_type' => 'authorization_code',
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : null;
    }

    public function canAccessCalendar(string $accessToken, string $calendarId): bool
    {
        try {
            $response = Http::withToken($accessToken)
                ->connectTimeout(3)
                ->timeout(10)
                ->get('https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId));
        } catch (ConnectionException) {
            return false;
        }

        return $response->successful();
    }

    public function createCalendar(string $accessToken, string $summary, string $timezone): ?string
    {
        try {
            $response = Http::withToken($accessToken)
                ->connectTimeout(3)
                ->timeout(10)
                ->post('https://www.googleapis.com/calendar/v3/calendars', [
                    'summary' => $summary,
                    'timeZone' => $timezone,
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $calendarId = $response->json('id');

        return is_string($calendarId) && $calendarId !== '' ? $calendarId : null;
    }
}
