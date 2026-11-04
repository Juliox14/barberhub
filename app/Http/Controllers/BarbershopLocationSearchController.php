<?php

namespace App\Http\Controllers;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use App\Support\NominatimGeocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BarbershopLocationSearchController extends Controller
{
    public function __invoke(Request $request, NominatimGeocoder $geocoder): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasVerifiedEmail(), 403);

        $slug = $request->input('barbershop_slug');
        $barbershop = is_string($slug) && $slug !== ''
            ? Barbershop::query()->where('slug', $slug)->first()
            : null;

        if ($user->is_platform_admin) {
            abort_if(is_string($slug) && $slug !== '' && $barbershop === null, 403);
            abort_if($barbershop !== null && $barbershop->status !== Barbershop::STATUS_ACTIVE, 403);
        } else {
            abort_if($barbershop === null || $barbershop->status !== Barbershop::STATUS_ACTIVE, 403);

            $isActiveOwner = $user->memberships()
                ->where('barbershop_id', $barbershop->id)
                ->where('status', Membership::STATUS_ACTIVE)
                ->where('role', Membership::ROLE_OWNER)
                ->exists();

            abort_unless($isActiveOwner, 403);
        }

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:4', 'max:255'],
            'barbershop_slug' => ['nullable', 'string', 'max:255'],
        ], [
            'q.required' => 'Escribe una búsqueda de dirección comercial de al menos 4 caracteres.',
            'q.min' => 'La búsqueda debe tener al menos 4 caracteres.',
            'q.max' => 'La búsqueda no puede superar los 255 caracteres.',
        ]);
        $query = trim($validated['q']);

        if (mb_strlen($query) < 4) {
            return response()->json(['message' => 'La búsqueda debe tener al menos 4 caracteres.'], 422);
        }

        $result = $geocoder->search($query);

        if ($result['status'] === 'limited') {
            return response()->json(['message' => 'Hay otra búsqueda en curso o el servicio está en pausa. Inténtalo de nuevo en unos segundos.'], 429, ['Retry-After' => '2']);
        }

        if ($result['status'] !== 'ok') {
            return response()->json(['message' => 'No se pudo consultar el servicio. Puedes completar la ubicación manualmente.'], 503);
        }

        return response()->json(['results' => $result['results']]);
    }
}
