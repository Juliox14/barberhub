<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\AccountLoginGuard;
use App\Support\SecurityAuditLogger;
use App\Support\SingleDeviceSession;
use App\Support\TwoFactorLogin;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->session()->forget([SingleDeviceSession::PENDING_KEY, TwoFactorLogin::PENDING_KEY, TwoFactorLogin::VERIFIED_KEY]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(SingleDeviceSession $sessions, SecurityAuditLogger $auditLogger, AccountLoginGuard $loginGuard): User
    {
        $email = $this->string('email')->toString();

        if ($loginGuard->lockedUser($email) !== null) {
            $auditLogger->record('login.failed', request: $this);

            throw ValidationException::withMessages([
                'email' => trans('auth.temporarily_blocked'),
            ]);
        }

        $this->ensureIsNotRateLimited();

        $user = $sessions->verifyCredentials($email, $this->string('password')->toString());

        if ($user === null) {
            $this->throwFailedAuthentication($auditLogger, $loginGuard, $email);
        }

        $loginGuard->clearFailedWindow($user);
        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    private function throwFailedAuthentication(SecurityAuditLogger $auditLogger, AccountLoginGuard $loginGuard, string $email): never
    {
        $auditLogger->record('login.failed', request: $this);
        $loginGuard->recordFailedAttempt($email);
        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => $loginGuard->lockedUser($email) !== null
                ? trans('auth.temporarily_blocked')
                : trans('auth.failed'),
        ]);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
