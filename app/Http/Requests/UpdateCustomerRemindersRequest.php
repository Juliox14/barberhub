<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCustomerRemindersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'frequency_months' => ['nullable', 'integer', Rule::in([1, 2, 3])],
            'frequency_weeks' => ['nullable', 'integer', Rule::in([2])],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->boolean('enabled') || $validator->errors()->hasAny(['enabled', 'frequency_months', 'frequency_weeks'])) {
                return;
            }

            $hasMonths = $this->input('frequency_months') !== null;
            $hasWeeks = $this->input('frequency_weeks') !== null;
            if ($hasMonths === $hasWeeks) {
                $validator->errors()->add('frequency_months', 'Selecciona una sola frecuencia válida.');
            }
        }];
    }
}
