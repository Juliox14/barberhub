<?php

namespace App\Http\Middleware;

use App\Support\AdaptiveChallengeTracker;
use App\Support\RecaptchaService;
use App\Support\RecaptchaVerification;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdaptiveRecaptcha
{
    public function __construct(
        private readonly AdaptiveChallengeTracker $tracker,
        private readonly RecaptchaService $recaptcha,
        private readonly RecaptchaVerification $verification,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tracker = $this->tracker;
        $recaptcha = $this->recaptcha;
        $verification = $this->verification;
        $flow = $this->flow($request);
        if ($flow === null) {
            return $next($request);
        }

        $token = null;
        if ($request->isMethod('POST')) {
            $token = $request->input('g-recaptcha-response');
            $request->request->remove('g-recaptcha-response');
            $request->query->remove('g-recaptcha-response');
        }

        if (! config('recaptcha.enabled')) {
            $verification->forgetAll($request);

            return $next($request);
        }

        $required = $tracker->challengeRequired($flow, $request->ip());

        if ($request->isMethod('GET')) {
            $required = $required && ! $verification->isRemembered($request, $flow, $request->ip());
            ViewFacade::share([
                'recaptchaRequired' => $required,
                'recaptchaAvailable' => $required && $recaptcha->configured(),
                'recaptchaSiteKey' => $required ? $recaptcha->siteKey() : null,
            ]);

            try {
                return $next($request);
            } finally {
                ViewFacade::share([
                    'recaptchaRequired' => false,
                    'recaptchaAvailable' => false,
                    'recaptchaSiteKey' => null,
                ]);
            }
        }

        $hasProof = $required && $verification->isRemembered($request, $flow, $request->ip());
        if ($required && ! $hasProof) {
            if (! is_string($token) || mb_strlen($token) > 4096 || ! $recaptcha->verify($token, $request->ip())) {
                throw ValidationException::withMessages([
                    'g-recaptcha-response' => $recaptcha->configured()
                        ? 'No pudimos verificar el desafío. Inténtalo nuevamente.'
                        : 'La verificación de seguridad no está disponible. Inténtalo más tarde.',
                ]);
            }

            $verification->rememberSuccessfulChallenge($request, $flow, $request->ip());
        }

        $tracker->admit($flow, $request->ip());

        return $next($request);
    }

    private function flow(Request $request): ?string
    {
        return match (true) {
            $request->routeIs('login', 'login.store') => 'login',
            $request->routeIs('register', 'register.store') => 'register',
            $request->routeIs('password.request', 'password.email') => 'forgot-password',
            default => null,
        };
    }
}
