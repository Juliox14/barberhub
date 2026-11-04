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
            'country_code' => ['nullable', 'string', 'size:2', Rule::in(self::countryCodes())],
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

    /** @return array<int, string> */
    private static function countryCodes(): array
    {
        return explode(' ', 'AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW');
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
