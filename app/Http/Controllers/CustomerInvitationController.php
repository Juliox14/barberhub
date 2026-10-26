<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\CustomerInvitationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CustomerInvitationController extends Controller
{
    public function show(Request $request, string $token, CustomerInvitationService $invitations): View|RedirectResponse
    {
        $invitation = $invitations->activeInvitation($token);

        if ($invitation === null) {
            throw new HttpException(410, 'La invitación venció o ya fue utilizada.');
        }

        if ($invitation->customer->user_id !== null) {
            throw new HttpException(409, 'Esta ficha de cliente ya está vinculada a una cuenta.');
        }

        $user = $request->user();

        if ($user instanceof User) {
            abort_unless(hash_equals(mb_strtolower($invitation->email), mb_strtolower($user->email)), 403, 'Esta invitación fue emitida para otro correo.');

            return view('customer-invitations.show', [
                'invitation' => $invitation,
                'token' => $token,
                'existingAccount' => true,
            ]);
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($invitation->email)])->exists()) {
            return redirect()->guest(route('login'));
        }

        return view('customer-invitations.show', [
            'invitation' => $invitation,
            'token' => $token,
            'existingAccount' => false,
        ]);
    }

    public function register(Request $request, string $token, CustomerInvitationService $invitations): RedirectResponse
    {
        abort_if($request->user() !== null, 403, 'Cierra sesión para completar una invitación.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        try {
            $result = $invitations->createAccountAndAccept($token, $validated);
        } catch (ValidationException $exception) {
            $request->session()->put('url.intended', route('customer-invitations.show', $token));

            return redirect()->route('login')->withErrors($exception->errors());
        }

        event(new Registered($result['user']));
        Auth::login($result['user']);
        $request->session()->regenerate();

        return redirect('/')
            ->with('status', 'Tu cuenta quedó vinculada con el historial de '.$result['invitation']->customer->name.'.');
    }

    public function accept(Request $request, string $token, CustomerInvitationService $invitations): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $invitation = $invitations->accept($token, $user);

        return redirect('/')
            ->with('status', 'Tu cuenta quedó vinculada con el historial de '.$invitation->customer->name.'.');
    }
}
