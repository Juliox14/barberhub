<?php

namespace App\Support;

use App\Models\Barbershop;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class BarbershopLocation
{
    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'country_code' => ['nullable', 'string', 'size:2', Rule::in(array_keys(CountryNames::all()))],
            'region' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'street_address' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'decimal:0,7', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'decimal:0,7', 'required_with:latitude'],
        ];
    }

    /** @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public static function submittedFields(Request $request, array $validated): array
    {
        $fields = ['country_code', 'region', 'city', 'street_address', 'latitude', 'longitude'];
        $submitted = [];
        foreach ($fields as $field) {
            if ($request->exists($field)) {
                $submitted[$field] = $validated[$field] ?? null;
            }
        }

        return $submitted;
    }

    public static function validateFinalCoordinatePair(array $submitted, ?Barbershop $barbershop = null): void
    {
        $latitude = array_key_exists('latitude', $submitted) ? $submitted['latitude'] : $barbershop?->latitude;
        $longitude = array_key_exists('longitude', $submitted) ? $submitted['longitude'] : $barbershop?->longitude;

        if (filled($latitude) !== filled($longitude)) {
            $missingField = filled($latitude) ? 'longitude' : 'latitude';

            throw ValidationException::withMessages([
                $missingField => 'La latitud y la longitud deben indicarse juntas.',
            ]);
        }
    }

    public static function normalizeCountryCode(Request $request): void
    {
        if ($request->has('country_code') && is_string($request->input('country_code'))) {
            $request->merge(['country_code' => strtoupper(trim($request->input('country_code')))]);
        }
    }
}
