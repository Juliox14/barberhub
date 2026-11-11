<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\User;
use App\Support\GoogleCalendarClient;
use App\Support\TenantDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BarberGoogleCalendarController extends Controller
{
    private const STATE_SESSION_KEY = 'google_calendar_oauth_state';

    private const REQUIRED_SCOPE = 'https://www.googleapis.com/auth/calendar.app.created';

    public function show(Request $request, Barbershop $barbershop): View
    {
        $barber = $this->selfBarber($request, $barbershop);
        $connection = $barber->googleCalendarConnection;

        return view('tenant.google-calendar', [
            'barbershop' => $barbershop,
            'connection' => $connection,
            'connected' => $connection?->connected_at !== null,
            'roleLabel' => TenantDashboard::roleLabel(TenantDashboard::membershipFor($request->user(), $barbershop)),
            'activePage' => 'google-calendar',
        ]);
    }

    public function connect(Request $request, Barbershop $barbershop): RedirectResponse
    {
        $barber = $this->selfBarber($request, $barbershop);
        $clientId = config('services.google_calendar.client_id');
        $clientSecret = config('services.google_calendar.client_secret');
        $redirectUri = config('services.google_calendar.redirect');

        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '' || ! $this->validCallback($redirectUri)) {
            return redirect()->route('tenant.google-calendar.show', $barbershop)
                ->with('calendar_error', 'La conexión con Google Calendar no está configurada todavía.');
        }

        $state = bin2hex(random_bytes(32));
        $request->session()->put(self::STATE_SESSION_KEY, [
            'state' => $state,
            'user_id' => $request->user()->getKey(),
            'barber_id' => $barber->getKey(),
            'barbershop_id' => $barbershop->getKey(),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::REQUIRED_SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986));
    }

    public function callback(Request $request, GoogleCalendarClient $googleCalendar): RedirectResponse
    {
        $pending = $request->session()->pull(self::STATE_SESSION_KEY);
        if (! is_array($pending)
            || ! is_string($pending['state'] ?? null)
            || ! is_string($request->query('state'))
            || ! hash_equals($pending['state'], $request->query('state'))
            || ($pending['expires_at'] ?? 0) <= now()->timestamp
            || (int) ($pending['user_id'] ?? 0) !== (int) $request->user()->getKey()) {
            return $this->fallback('La solicitud de conexión expiró o no es válida. Inténtalo de nuevo.');
        }

        $barber = Barber::query()
            ->whereKey($pending['barber_id'] ?? 0)
            ->where('user_id', $request->user()->getKey())
            ->where('barbershop_id', $pending['barbershop_id'] ?? 0)
            ->where('active', true)
            ->first();
        $barbershop = Barbershop::query()->find($pending['barbershop_id'] ?? 0);

        if ($barber === null || $barbershop === null) {
            return $this->fallback('No se pudo validar tu perfil de barbero para esta conexión.');
        }

        $user = $request->user();
        if (! TenantDashboard::canUseTenantDashboard($user, $barbershop)) {
            return $this->fallback('Ya no tienes acceso a esta barbería. Inicia una nueva solicitud desde tu panel.');
        }

        if ($request->filled('error')) {
            return redirect()->route('tenant.google-calendar.show', $barbershop)
                ->with('calendar_error', 'No se autorizó la conexión con Google Calendar.');
        }

        $code = $request->query('code');
        $redirectUri = config('services.google_calendar.redirect');
        if (! is_string($code) || $code === '' || ! $this->validCallback($redirectUri)) {
            return redirect()->route('tenant.google-calendar.show', $barbershop)
                ->with('calendar_error', 'Google no devolvió una autorización válida. Inténtalo de nuevo.');
        }

        $tokens = $googleCalendar->exchangeCode($code, $redirectUri);
        if (! is_array($tokens)) {
            return redirect()->route('tenant.google-calendar.show', $barbershop)
                ->with('calendar_error', 'Google no devolvió una autorización válida. Inténtalo de nuevo.');
        }

        $scope = $tokens['scope'] ?? null;
        $accessToken = $tokens['access_token'] ?? null;
        $refreshToken = $tokens['refresh_token'] ?? null;
        if (! is_string($scope)
            || ! in_array(self::REQUIRED_SCOPE, preg_split('/\s+/', $scope, -1, PREG_SPLIT_NO_EMPTY), true)
            || ! is_string($accessToken)
            || $accessToken === ''
            || ! is_string($refreshToken)
            || $refreshToken === '') {
            return redirect()->route('tenant.google-calendar.show', $barbershop)
                ->with('calendar_error', 'Google no concedió los permisos necesarios para conectar el calendario.');
        }

        $connection = $barber->googleCalendarConnection;
        $calendarId = $connection?->google_calendar_id;
        if (! is_string($calendarId) || $calendarId === '' || ! $googleCalendar->canAccessCalendar($accessToken, $calendarId)) {
            $calendarId = $googleCalendar->createCalendar(
                $accessToken,
                'BarberHub · '.$barber->display_name,
                $barbershop->timezone,
            );
        }

        if (! is_string($calendarId) || $calendarId === '') {
            return redirect()->route('tenant.google-calendar.show', $barbershop)
                ->with('calendar_error', 'No se pudo preparar tu calendario de BarberHub. Inténtalo más tarde.');
        }

        if (! TenantDashboard::canUseTenantDashboard($user, $barbershop)) {
            return $this->fallback('Ya no tienes acceso a esta barbería. Inicia una nueva solicitud desde tu panel.');
        }

        $barber->googleCalendarConnection()->updateOrCreate([], [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_expires_at' => now()->addSeconds(max(0, (int) ($tokens['expires_in'] ?? 0))),
            'google_calendar_id' => $calendarId,
            'connected_at' => now(),
        ]);

        return redirect()->route('tenant.google-calendar.show', $barbershop)
            ->with('status', 'Google Calendar conectado correctamente.');
    }

    public function disconnect(Request $request, Barbershop $barbershop): RedirectResponse
    {
        $barber = $this->selfBarber($request, $barbershop);
        $connection = $barber->googleCalendarConnection;
        if ($connection !== null) {
            $connection->forceFill([
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null,
                'connected_at' => null,
            ])->save();
        }

        return redirect()->route('tenant.google-calendar.show', $barbershop)
            ->with('status', 'Google Calendar desconectado. Tu calendario y sus eventos no se eliminaron.');
    }

    private function selfBarber(Request $request, Barbershop $barbershop): Barber
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return Barber::query()
            ->where('barbershop_id', $barbershop->getKey())
            ->where('user_id', $user->getKey())
            ->where('active', true)
            ->firstOrFail();
    }

    private function validCallback(mixed $redirectUri): bool
    {
        return is_string($redirectUri)
            && $redirectUri !== ''
            && filter_var($redirectUri, FILTER_VALIDATE_URL) !== false
            && parse_url($redirectUri, PHP_URL_SCHEME) === 'https';
    }

    private function fallback(string $message): RedirectResponse
    {
        return redirect()->route('dashboard')->with('calendar_error', $message);
    }
}
