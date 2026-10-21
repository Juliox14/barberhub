<?php

namespace App\Support;

use DateTimeZone;

final class LatinAmericanTimezones
{
    /** @var array<string, string> */
    private const COUNTRIES = [
        'AR' => 'Argentina', 'BO' => 'Bolivia', 'BR' => 'Brasil', 'CL' => 'Chile',
        'CO' => 'Colombia', 'CR' => 'Costa Rica', 'CU' => 'Cuba', 'DO' => 'República Dominicana',
        'EC' => 'Ecuador', 'SV' => 'El Salvador', 'GT' => 'Guatemala', 'GF' => 'Guayana Francesa',
        'GP' => 'Guadalupe', 'HT' => 'Haití', 'HN' => 'Honduras', 'MQ' => 'Martinica',
        'MX' => 'México', 'NI' => 'Nicaragua', 'PA' => 'Panamá', 'PY' => 'Paraguay',
        'PE' => 'Perú', 'PR' => 'Puerto Rico', 'BL' => 'San Bartolomé', 'MF' => 'San Martín',
        'UY' => 'Uruguay', 'VE' => 'Venezuela',
    ];

    /** @return array<string, array<string, string>> */
    public static function groupedOptions(): array
    {
        $options = [];

        foreach (self::COUNTRIES as $countryCode => $countryName) {
            $identifiers = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $countryCode);
            $countryOptions = [];

            foreach ($identifiers as $identifier) {
                $city = substr($identifier, strpos($identifier, '/') + 1);
                $label = str_replace(['_', '/'], [' ', ' · '], $city);
                $countryOptions[$identifier] = $label.' ('.$identifier.') · '.$countryName;
            }

            if ($countryOptions !== []) {
                $options[$countryName] = $countryOptions;
            }
        }

        return $options;
    }

    /** @return list<string> */
    public static function identifiers(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::groupedOptions())));
    }
}
