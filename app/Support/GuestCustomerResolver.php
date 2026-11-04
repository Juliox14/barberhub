<?php

namespace App\Support;

use App\Models\Barbershop;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;

final class GuestCustomerResolver
{
    /**
     * Resolve inside the booking transaction so the shop lock lasts through scheduling.
     *
     * @param  array{name: string, email: string, phone: ?string}  $contact
     */
    public function resolve(Barbershop $barbershop, array $contact): Customer
    {
        Barbershop::query()->whereKey($barbershop->id)->lockForUpdate()->firstOrFail();

        $email = mb_strtolower(trim($contact['email']));
        $name = $this->normalizeName($contact['name']);
        $matches = $barbershop->customers()
            ->whereNull('user_id')
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->orderBy('id')
            ->cursor()
            ->filter(fn (Customer $customer): bool => $this->normalizeName($customer->name) === $name)
            ->take(2)
            ->collect();

        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($matches->count() > 1) {
            Log::notice('guest_customer.ambiguous_match', [
                'barbershop_id' => $barbershop->id,
                'candidate_ids' => $matches->pluck('id')->all(),
            ]);
        }

        return $barbershop->customers()->create([
            'user_id' => null,
            'name' => $contact['name'],
            'email' => $email,
            'phone' => $contact['phone'],
        ]);
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
    }
}
