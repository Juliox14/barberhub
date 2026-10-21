<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

final class CompleteBarberInvitationController extends Controller
{
    public function create(Request $request, string $token): View
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        abort_unless(
            $user?->email_verified_at === null
                && $this->isEligibleBarber($user)
                && Password::broker()->tokenExists($user, $token),
            403,
        );

        return view('auth.accept-barber-invitation', [
            'email' => $user->email,
            'token' => $token,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'password_confirmation' => ['required', 'same:password'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        abort_unless($user?->email_verified_at === null && $this->isEligibleBarber($user), 403);

        $tokenTable = config('auth.passwords.'.config('auth.defaults.passwords').'.table');
        $status = DB::transaction(function () use ($tokenTable, $validated): string {
            DB::table($tokenTable)
                ->where('email', $validated['email'])
                ->lockForUpdate()
                ->first();

            return Password::broker()->reset(
                [
                    'email' => $validated['email'],
                    'token' => $validated['token'],
                    'password' => $validated['password'],
                    'password_confirmation' => $validated['password_confirmation'],
                ],
                function (User $user, string $password): void {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'email_verified_at' => now(),
                        'remember_token' => Str::random(60),
                    ])->save();

                    event(new PasswordReset($user));
                },
            );
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('login')
                ->with('status', 'Contraseña configurada y correo verificado. Ya puedes iniciar sesión.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'El enlace de invitación ya se utilizó o venció. Solicita una nueva invitación.']);
    }

    private function isEligibleBarber(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $hasActiveBarberMembership = $user->memberships()
            ->where('role', Membership::ROLE_BARBER)
            ->where('status', Membership::STATUS_ACTIVE)
            ->exists();

        return $hasActiveBarberMembership
            && $user->barberProfiles()->where('active', true)->exists();
    }
}
