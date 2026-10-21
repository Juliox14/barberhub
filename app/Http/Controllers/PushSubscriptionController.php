<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\PushEndpoint;
use App\Support\TenantDashboard;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PushSubscriptionController extends Controller
{
    public function store(Request $request, Barbershop $barbershop): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canReceivePush($user, $barbershop), 403);

        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
            'keys' => ['required', 'array:p256dh,auth'],
            'keys.p256dh' => ['required', 'string', 'max:128'],
            'keys.auth' => ['required', 'string', 'max:64'],
        ]);

        $endpoint = $validated['endpoint'];
        $publicKey = $validated['keys']['p256dh'];
        $authKey = $validated['keys']['auth'];

        if (! PushEndpoint::isValid($endpoint)) {
            throw ValidationException::withMessages(['endpoint' => 'El endpoint de notificaciones no es válido.']);
        }

        $decodedAuthKey = PushEndpoint::decodeKey($authKey);

        if (! PushEndpoint::isP256PublicKey($publicKey) || $decodedAuthKey === null || strlen($decodedAuthKey) !== 16) {
            throw ValidationException::withMessages(['keys' => 'Las claves de notificación no tienen un formato válido.']);
        }

        $endpointHash = hash('sha256', $endpoint);
        $sessionHash = hash('sha256', $request->session()->getId());
        $subscription = PushSubscription::query()->where('endpoint_hash', $endpointHash)->first();

        if ($subscription !== null) {
            if ($subscription->user_id !== $user->id || $subscription->session_hash !== $sessionHash) {
                return response()->json(['message' => 'Este endpoint ya está asociado a otro navegador.'], 409);
            }

            $subscription->update([
                'endpoint' => $endpoint,
                'p256dh' => $publicKey,
                'auth' => $authKey,
            ]);

            return response()->json(['subscribed' => true]);
        }

        try {
            PushSubscription::query()->create([
                'user_id' => $user->id,
                'endpoint' => $endpoint,
                'endpoint_hash' => $endpointHash,
                'p256dh' => $publicKey,
                'auth' => $authKey,
                'session_hash' => $sessionHash,
            ]);
        } catch (QueryException $exception) {
            $subscription = PushSubscription::query()->where('endpoint_hash', $endpointHash)->first();

            if ($subscription === null) {
                throw $exception;
            }

            if ($subscription->user_id !== $user->id || $subscription->session_hash !== $sessionHash) {
                return response()->json(['message' => 'Este endpoint ya está asociado a otro navegador.'], 409);
            }

            $subscription->update(['p256dh' => $publicKey, 'auth' => $authKey]);
        }

        return response()->json(['subscribed' => true], 201);
    }

    public function destroy(Request $request, Barbershop $barbershop, PushSubscription $subscription): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canReceivePush($user, $barbershop), 403);

        abort_unless(
            $subscription->user_id === $user->id
            && $subscription->session_hash === hash('sha256', $request->session()->getId()),
            404,
        );

        $subscription->delete();

        return response()->json(['subscribed' => false]);
    }

    private function canReceivePush(User $user, Barbershop $barbershop): bool
    {
        $membership = TenantDashboard::membershipFor($user, $barbershop);

        return $membership?->role === Membership::ROLE_BARBER
            && Barber::query()
                ->where('barbershop_id', $barbershop->id)
                ->where('user_id', $user->id)
                ->where('active', true)
                ->exists();
    }
}
