<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PasswordPolicy;
use App\Support\SingleDeviceSession;
use App\Support\TwoFactorAuthentication;
use App\Support\TwoFactorLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionStatusController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $ownerToken = $request->session()->get(SingleDeviceSession::OWNER_TOKEN_KEY);

        if (! $user instanceof User || ! is_string($ownerToken)) {
            return $this->expiredResponse($request);
        }

        $owner = User::query()
            ->find($user->getKey());

        if ($owner === null || $owner->active_session_at === null
            || $owner->active_session_at->lt(now()->subMinutes(config('session.lifetime')))) {
            return $this->expiredResponse($request);
        }

        if (! is_string($owner->active_session_id)
            || ! hash_equals($owner->active_session_id, $ownerToken)) {
            return $this->revokedResponse($request);
        }

        if (PasswordPolicy::expired($owner)) {
            return response()->json(['status' => 'password_expired'], 401)
                ->header('Cache-Control', 'no-store, private');
        }
        $factor = app(TwoFactorAuthentication::class);
        $login = app(TwoFactorLogin::class);
        if ($factor->enabled($owner) && ! $login->verified($request, $owner)) {
            return response()->json(['status' => 'two_factor_challenge', 'redirect' => route('two-factor.challenge')], 403)
                ->header('Cache-Control', 'no-store, private');
        }
        if ($login->enrollmentOverdue($owner)) {
            return response()->json(['status' => 'two_factor_required', 'redirect' => route('two-factor.settings')], 403)
                ->header('Cache-Control', 'no-store, private');
        }

        return response()->json(['status' => 'current'])
            ->header('Cache-Control', 'no-store, private');
    }

    private function revokedResponse(Request $request): JsonResponse
    {
        $this->endSession($request);

        return response()->json(['status' => 'revoked'], 401)
            ->header('Cache-Control', 'no-store, private');
    }

    private function expiredResponse(Request $request): JsonResponse
    {
        $this->endSession($request);

        return response()->json(['status' => 'expired'], 401)
            ->header('Cache-Control', 'no-store, private');
    }

    private function endSession(Request $request): void
    {
        Auth::guard('web')->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
