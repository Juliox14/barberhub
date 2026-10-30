<?php

namespace App\Http\Requests;

use App\Models\ModuleContainment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformModuleReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_platform_admin;
    }

    /**
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'module' => ['required', 'string', Rule::in(array_keys(ModuleContainment::modules()))],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'module.in' => 'Selecciona un módulo disponible para levantar la restricción.',
            'reason.required' => 'Indica el motivo para levantar la restricción.',
        ];
    }
}
