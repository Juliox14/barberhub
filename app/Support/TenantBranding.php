<?php

namespace App\Support;

use App\Models\Barbershop;

final class TenantBranding
{
    /**
     * @return array{primary: string, accent: string, primary_foreground: string, accent_foreground: string, surface: string, description: string}
     */
    public static function resolve(Barbershop $barbershop): array
    {
        $primary = self::canonicalColor($barbershop->brand_primary_color, '#496C35');
        $accent = self::canonicalColor($barbershop->brand_accent_color, '#B8885A');

        return [
            'primary' => $primary,
            'accent' => $accent,
            'primary_foreground' => self::readableForeground($primary),
            'accent_foreground' => self::readableForeground($accent),
            'surface' => '#F8F7F4',
            'description' => (string) ($barbershop->brand_description ?? ''),
        ];
    }

    private static function canonicalColor(?string $color, string $fallback): string
    {
        return is_string($color) && preg_match('/\A#[0-9A-Fa-f]{6}\z/', $color) === 1
            ? strtoupper($color)
            : $fallback;
    }

    private static function readableForeground(string $background): string
    {
        $red = hexdec(substr($background, 1, 2)) / 255;
        $green = hexdec(substr($background, 3, 2)) / 255;
        $blue = hexdec(substr($background, 5, 2)) / 255;

        $luminance = 0.2126 * self::linearize($red)
            + 0.7152 * self::linearize($green)
            + 0.0722 * self::linearize($blue);
        $blackContrast = ($luminance + 0.05) / 0.05;
        $whiteContrast = 1.05 / ($luminance + 0.05);

        return $blackContrast >= $whiteContrast ? '#000000' : '#FFFFFF';
    }

    private static function linearize(float $channel): float
    {
        return $channel <= 0.04045
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4;
    }
}
