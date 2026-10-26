<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\CustomerInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CustomerInvitationController extends Controller
{
    public function show(Request $request, string $token, CustomerInvitationService $invitations): View
    {
        $invitation = $invitations->activeInvitation($token);

        if ($invitation === null) {
            throw new HttpException(410, 'La invitación venció o ya fue utilizada.');
        }

        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless(hash_equals(mb_strtolower($invitation->email), mb_strtolower($user->email)), 403, 'Esta invitación fue emitida para otro correo.');

        if ($invitation->customer->user_id !== null) {
            throw new HttpException(409, 'Esta ficha de cliente ya está vinculada a una cuenta.');
        }

        return view('customer-invitations.show', [
            'invitation' => $invitation,
            'token' => $token,
        ]);
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
