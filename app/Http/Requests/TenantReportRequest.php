<?php

namespace App\Http\Requests;

use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use App\Support\TenantDashboard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TenantReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $barbershop = $this->route('barbershop');

        if (! $user instanceof User || ! $barbershop instanceof Barbershop) {
            return false;
        }

        if ($user->is_platform_admin) {
            return true;
        }

        $membership = TenantDashboard::membershipFor($user, $barbershop);

        return in_array($membership?->role, [Membership::ROLE_OWNER, Membership::ROLE_ADMIN], true);
    }

    protected function prepareForValidation(): void
    {
        $barbershop = $this->route('barbershop');

        if (! $barbershop instanceof Barbershop) {
            return;
        }

        $today = CarbonImmutable::now($barbershop->timezone);

        $this->merge([
            'from' => $this->input('from', $today->startOfMonth()->toDateString()),
            'to' => $this->input('to', $today->toDateString()),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $barbershop = $this->route('barbershop');

        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
            'barber_id' => [
                'nullable',
                'integer',
                Rule::exists(Barber::class, 'id')->where('barbershop_id', $barbershop?->id),
            ],
            'service_id' => [
                'nullable',
                'integer',
                Rule::exists(Service::class, 'id')->where('barbershop_id', $barbershop?->id),
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['from', 'to'])) {
                return;
            }

            $barbershop = $this->route('barbershop');

            if (! $barbershop instanceof Barbershop) {
                return;
            }

            $from = CarbonImmutable::createFromFormat('!Y-m-d', $this->validated('from'), $barbershop->timezone);
            $to = CarbonImmutable::createFromFormat('!Y-m-d', $this->validated('to'), $barbershop->timezone);

            if ($to->lessThan($from)) {
                $validator->errors()->add('to', 'La fecha final debe ser igual o posterior a la fecha inicial.');

                return;
            }

            if ($from->diff($to)->days > 365) {
                $validator->errors()->add('to', 'El periodo no puede superar 366 días.');
            }
        }];
    }
}
