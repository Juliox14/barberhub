<?php

namespace App\Support;

use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\CustomerInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CustomerInvitationService
{
    /**
     * @return array{invitation: CustomerInvitation, token: string}
     */
    public function issue(User $issuer, Barbershop $barbershop, Customer $customer, string $email): array
    {
        $token = Str::random(64);
        $invitation = CustomerInvitation::query()->create([
            'barbershop_id' => $barbershop->id,
            'customer_id' => $customer->id,
            'issued_by' => $issuer->id,
            'email' => Str::lower($email),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours(48),
        ]);

        return ['invitation' => $invitation, 'token' => $token];
    }

    public function activeInvitation(string $token): ?CustomerInvitation
    {
        return $this->activeQuery($token)->with(['barbershop', 'customer'])->first();
    }

    public function intendedPathForUser(?string $intendedUrl, User $user): ?string
    {
        $token = $this->invitationTokenFromUrl($intendedUrl);

        if ($token === null) {
            return null;
        }

        $invitation = $this->activeInvitation($token);

        if ($invitation === null || ! $this->emailsMatch($invitation->email, $user->email)) {
            return null;
        }

        return route('customer-invitations.show', $token, false);
    }

    public function intendedPathForRegistration(?string $intendedUrl, string $email): ?string
    {
        $token = $this->invitationTokenFromUrl($intendedUrl);

        if ($token === null) {
            return null;
        }

        $invitation = $this->activeInvitation($token);

        if ($invitation === null || ! $this->emailsMatch($invitation->email, $email)) {
            throw ValidationException::withMessages([
                'email' => 'Usa el correo de la invitación vigente para crear tu cuenta.',
            ]);
        }

        return route('customer-invitations.show', $token, false);
    }

    public function accept(string $token, User $user): CustomerInvitation
    {
        return DB::transaction(function () use ($token, $user): CustomerInvitation {
            $invitation = $this->activeQuery($token)->lockForUpdate()->first();

            if ($invitation === null) {
                throw new HttpException(410, 'La invitación venció o ya fue utilizada.');
            }

            if (! $this->emailsMatch($invitation->email, $user->email)) {
                throw new HttpException(403, 'Esta invitación fue emitida para otro correo.');
            }

            $customer = Customer::query()
                ->whereKey($invitation->customer_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($customer->user_id !== null) {
                throw new HttpException(409, 'Esta ficha de cliente ya está vinculada a una cuenta.');
            }

            $customer->user_id = $user->id;
            $customer->save();

            $invitation->forceFill([
                'consumed_at' => now(),
                'accepted_by' => $user->id,
            ])->save();

            return $invitation;
        });
    }

    private function activeQuery(string $token): Builder
    {
        return CustomerInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now());
    }

    private function invitationTokenFromUrl(?string $url): ?string
    {
        if ($url === null || $this->isExternalUrl($url)) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || preg_match('#^/customer-invitations/([A-Za-z0-9]{64})$#', $path, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function isExternalUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host !== null && $host !== parse_url(config('app.url'), PHP_URL_HOST);
    }

    private function emailsMatch(string $expectedEmail, string $actualEmail): bool
    {
        return hash_equals(Str::lower($expectedEmail), Str::lower($actualEmail));
    }
}
