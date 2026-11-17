<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GoogleCalendarClient
{
    /** @return array<string, mixed>|null */
    public function exchangeCode(string $code, string $redirectUri): ?array
    {
        $response = $this->requestToken(['code' => $code, 'redirect_uri' => $redirectUri, 'grant_type' => 'authorization_code']);

        return $response?->successful() && is_array($response->json()) ? $response->json() : null;
    }

    public function canAccessCalendar(string $accessToken, string $calendarId): bool
    {
        try {
            return $this->send($accessToken)->get('https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId))->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    public function createCalendar(string $accessToken, string $summary, string $timezone): ?string
    {
        try {
            $response = $this->send($accessToken)->post('https://www.googleapis.com/calendar/v3/calendars', ['summary' => $summary, 'timeZone' => $timezone]);
        } catch (ConnectionException) {
            return null;
        }
        $calendarId = $response->successful() ? $response->json('id') : null;

        return is_string($calendarId) && $calendarId !== '' ? $calendarId : null;
    }

    /** @param array<string, mixed> $event */
    public function insertEvent(string $accessToken, string $calendarId, string $eventId, array $event): Response
    {
        return $this->eventRequest(fn (): Response => $this->send($accessToken)->post($this->eventUrl($calendarId).'?sendUpdates=none', ['id' => $eventId, ...$event]));
    }

    /** @param array<string, mixed> $event */
    public function updateEvent(string $accessToken, string $calendarId, string $eventId, array $event): Response
    {
        return $this->eventRequest(fn (): Response => $this->send($accessToken)->put($this->eventUrl($calendarId, $eventId).'?sendUpdates=none', ['id' => $eventId, ...$event]));
    }

    public function deleteEvent(string $accessToken, string $calendarId, string $eventId): Response
    {
        return $this->eventRequest(fn (): Response => $this->send($accessToken)->delete($this->eventUrl($calendarId, $eventId).'?sendUpdates=none'));
    }

    public function refreshToken(string $refreshToken): ?Response
    {
        return $this->requestToken(['refresh_token' => $refreshToken, 'grant_type' => 'refresh_token']);
    }

    /** @param callable(): Response $request */
    private function eventRequest(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException) {
            throw new RuntimeException('Google Calendar no está disponible temporalmente.');
        }
    }

    /** @param array<string, string> $payload */
    private function requestToken(array $payload): ?Response
    {
        try {
            return Http::asForm()->connectTimeout(3)->timeout(10)->post('https://oauth2.googleapis.com/token', [
                ...$payload,
                'client_id' => config('services.google_calendar.client_id'),
                'client_secret' => config('services.google_calendar.client_secret'),
            ]);
        } catch (ConnectionException) {
            return null;
        }
    }

    private function send(string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)->connectTimeout(3)->timeout(10);
    }

    private function eventUrl(string $calendarId, ?string $eventId = null): string
    {
        $url = 'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId).'/events';

        return $eventId === null ? $url : $url.'/'.rawurlencode($eventId);
    }
}
