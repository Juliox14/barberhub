<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CustomerInvitationService;
use App\Support\SingleDeviceSession;
use App\Support\TenantDashboard;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, CustomerInvitationService $invitations, SingleDeviceSession $sessions): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $intendedUrl = $request->session()->get('url.intended');
        $invitationPath = $invitations->intendedPathForRegistration($intendedUrl, $validated['email']);
        $request->session()->forget('url.intended');

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'client',
        ]);

        event(new Registered($user));

        $ownerToken = Str::random(64);

        if (! $sessions->claimAfterCredentials($user, $ownerToken)) {
            return redirect()->route('login')->withErrors([
                'email' => 'Ya hay una sesión activa para esta cuenta. Inicia sesión para solicitar el cambio de dispositivo.',
            ]);
        }

        $request->session()->put(SingleDeviceSession::OWNER_TOKEN_KEY, $ownerToken);
        Auth::login($user);

        if ($invitationPath !== null) {
            return redirect()->to($invitationPath);
        }

        $destination = TenantDashboard::destinationFor($user);

        abort_if($destination === null, 403, 'Todavía no tienes una barbería activa asignada.');

        return redirect()->route($destination['route'], $destination['parameters']);
    }
}
