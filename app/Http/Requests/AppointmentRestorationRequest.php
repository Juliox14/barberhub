<?php

namespace App\Http\Requests;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use App\Support\TenantDashboard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentRestorationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $barbershop = $this->route('barbershop');

        if (! $user instanceof User || ! $barbershop instanceof Barbershop) {
            return false;
        }

        $membership = TenantDashboard::membershipFor($user, $barbershop);

        return $user->is_platform_admin
            || in_array($membership?->role, [Membership::ROLE_OWNER, Membership::ROLE_ADMIN], true);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'min:1'],
            'expected_starts_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'expected_status' => ['required', Rule::in(['scheduled', 'cancelled', 'completed', 'no_show'])],
            'reason' => ['required', 'string', 'max:500', 'not_regex:/^\s*$/u'],
        ];
    }
}
