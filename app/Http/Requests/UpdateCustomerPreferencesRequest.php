<?php

namespace App\Http\Requests;

use App\Models\Barbershop;
use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $barbershop = $this->route('barbershop');

        return $barbershop instanceof Barbershop
            && Customer::query()
                ->where('barbershop_id', $barbershop->id)
                ->where('user_id', $this->user()?->id)
                ->exists();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $barbershop = $this->route('barbershop');

        return [
            'preferred_barber_id' => [
                'nullable',
                'integer',
                Rule::exists('barbers', 'id')->where(fn ($query) => $query
                    ->where('barbershop_id', $barbershop->id)
                    ->where('active', true)),
            ],
            'preferred_service_id' => [
                'nullable',
                'integer',
                Rule::exists('services', 'id')->where(fn ($query) => $query
                    ->where('barbershop_id', $barbershop->id)
                    ->where('active', true)),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
