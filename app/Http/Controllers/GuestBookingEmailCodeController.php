<?php

namespace App\Http\Controllers;

use App\Models\Barbershop;
use App\Support\GuestBookingVerification;
use App\Support\ModuleContainment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class GuestBookingEmailCodeController extends Controller
{
    public function store(Request $request, Barbershop $barbershop, GuestBookingVerification $verification, ModuleContainment $containment): JsonResponse
    {
        $this->ensureBookingIsAvailable($barbershop, $containment);
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $verification->issue($request, $barbershop, $validator->validated()['email']);

        return response()->json(['status' => 'Si el correo es válido, recibirás un código.']);
    }

    public function verify(Request $request, Barbershop $barbershop, GuestBookingVerification $verification, ModuleContainment $containment): JsonResponse
    {
        $this->ensureBookingIsAvailable($barbershop, $containment);
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:255'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $verification->verify($request, $barbershop, $validated['email'], $validated['code']);

        if (! $verification->verified($request, $barbershop, $validated['email'])) {
            return response()->json(['verified' => false], 422);
        }

        return response()->json(['verified' => true]);
    }

    private function ensureBookingIsAvailable(Barbershop $barbershop, ModuleContainment $containment): void
    {
        abort_unless($barbershop->status === Barbershop::STATUS_ACTIVE, 404);
        abort_if($containment->isSuspended($barbershop, 'appointments'), 404);
    }
}
