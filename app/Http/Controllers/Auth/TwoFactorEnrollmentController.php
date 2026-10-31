<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TwoFactorAuthentication;
use App\Support\TwoFactorLogin;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Response;

final class TwoFactorEnrollmentController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthentication $twoFactor,
        private readonly Google2FA $google2FA,
    ) {}

    public function show(Request $request): Response|View
    {
        $user = $this->authenticatedUser($request);

        return $this->privateResponse(view('auth.two-factor-settings', [
            'enabled' => $this->twoFactor->enabled($user),
            'required' => $this->twoFactor->required($user),
            'pending' => $user->two_factor_pending_secret !== null,
        ]));
    }

    public function begin(Request $request): Response|View
    {
        return DB::transaction(fn (): Response => $this->beginConfirmedEnrollment($request));
    }

    private function beginConfirmedEnrollment(Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        if (! $this->confirmPassword($request, $user)) {
            return back()->withErrors(['current_password' => 'La contraseña actual no es válida.']);
        }

        try {
            $secret = $this->twoFactor->begin($user);
        } catch (\DomainException) {
            abort(409);
        }

        app(TwoFactorLogin::class)->beginEnrollment($request, $user->fresh());
        $provisioningUri = $this->google2FA
            ->getQRCodeUrl(config('app.name'), $user->email, $secret);
        $renderer = new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd);
        $qrCode = (new Writer($renderer))->writeString($provisioningUri);

        return $this->privateResponse(view('auth.two-factor-settings', [
            'enabled' => false,
            'required' => $this->twoFactor->required($user),
            'pending' => true,
            'manualSecret' => $secret,
            'qrCode' => $qrCode,
        ]));
    }

    public function confirm(Request $request): Response|RedirectResponse
    {
        return DB::transaction(fn (): Response => $this->confirmEnrollment($request));
    }

    private function confirmEnrollment(Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        app(TwoFactorLogin::class)->requireEnrollmentConfirmation($request, $user);
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->except('code'));
        }

        $this->ensureAttemptAllowed($user);
        try {
            $recoveryCodes = $this->twoFactor->confirm($user, $request->string('code')->toString());
        } catch (\DomainException) {
            abort(409);
        }
        if ($recoveryCodes === null) {
            RateLimiter::hit($this->attemptKey($user), 60);

            return back()->withErrors(['code' => 'El código de autenticación no es válido.']);
        }

        app(TwoFactorLogin::class)->markVerified($request, $user->fresh());
        $request->session()->forget('two_factor.enrollment');

        return $this->privateResponse(view('auth.two-factor-recovery-codes', [
            'recoveryCodes' => $recoveryCodes,
            'title' => 'Guarda tus códigos de recuperación',
        ]));
    }

    public function regenerateRecoveryCodes(Request $request): Response|RedirectResponse
    {
        return DB::transaction(fn (): Response => $this->rotateRecoveryCodes($request));
    }

    private function rotateRecoveryCodes(Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        if (! $this->confirmPassword($request, $user)) {
            return back()->withErrors(['current_password' => 'La contraseña actual no es válida.']);
        }

        $factorCode = $request->string('code')->toString() ?: null;
        $recoveryCode = $request->string('recovery_code')->toString() ?: null;
        $this->ensureAttemptAllowed($user);
        if (! $this->twoFactor->verify($user, $factorCode, $recoveryCode)) {
            RateLimiter::hit($this->attemptKey($user), 60);

            return back()->withErrors(['code' => 'La confirmación de seguridad no es válida.']);
        }

        try {
            $recoveryCodes = $this->twoFactor->regenerateRecoveryCodes($user->fresh());
        } catch (\DomainException) {
            abort(409);
        }

        app(TwoFactorLogin::class)->markVerified($request, $user->fresh());

        return $this->privateResponse(view('auth.two-factor-recovery-codes', [
            'recoveryCodes' => $recoveryCodes,
            'title' => 'Nuevos códigos de recuperación',
        ]));
    }

    public function disable(Request $request): RedirectResponse
    {
        return DB::transaction(fn (): RedirectResponse => $this->disableConfirmedFactor($request));
    }

    private function disableConfirmedFactor(Request $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        if ($this->twoFactor->required($user)) {
            abort(403);
        }

        if (! $this->confirmPassword($request, $user)) {
            return back()->withErrors(['current_password' => 'La contraseña actual no es válida.']);
        }

        $factorCode = $request->string('code')->toString() ?: null;
        $recoveryCode = $request->string('recovery_code')->toString() ?: null;
        $this->ensureAttemptAllowed($user);
        if (! $this->twoFactor->verify($user, $factorCode, $recoveryCode)) {
            RateLimiter::hit($this->attemptKey($user), 60);

            return back()->withErrors(['code' => 'La confirmación de seguridad no es válida.']);
        }

        try {
            $this->twoFactor->disable($user->fresh());
        } catch (\DomainException) {
            abort(403);
        }

        return redirect()->route('two-factor.settings')->with('status', 'La autenticación en dos pasos se desactivó.');
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if (DB::transactionLevel() > 0) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            abort_unless((int) $lockedUser->security_session_version === (int) $user->security_session_version, 419);

            return $lockedUser;
        }

        return $user->fresh();
    }

    private function confirmPassword(Request $request, User $user): bool
    {
        $this->ensureAttemptAllowed($user);
        $valid = Hash::check($request->string('current_password')->toString(), (string) $user->password);
        if (! $valid) {
            RateLimiter::hit($this->attemptKey($user), 60);
        }

        return $valid;
    }

    private function ensureAttemptAllowed(User $user): void
    {
        abort_if(RateLimiter::tooManyAttempts($this->attemptKey($user), 6), 429);
    }

    private function attemptKey(User $user): string
    {
        return 'two-factor-attempts:'.$user->getKey();
    }

    private function privateResponse(Response|View $response): Response|View
    {
        if ($response instanceof Response) {
            return $response->header('Cache-Control', 'private, no-store');
        }

        return response($response)->header('Cache-Control', 'private, no-store');
    }
}
