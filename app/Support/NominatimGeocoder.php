<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

final class NominatimGeocoder
{
    private const CACHE_SECONDS = 86400;

    private const GLOBAL_COOLDOWN_SECONDS = 2;

    private const LOCK_SECONDS = 15;

    /**
     * @return array{status: 'ok'|'limited'|'unavailable', results: list<array<string, string>>}
     */
    public function search(string $query): array
    {
        $searchUrl = (string) config('services.nominatim.search_url');
        $userAgent = trim((string) config('services.nominatim.user_agent'));

        if (! config('services.nominatim.enabled') || parse_url($searchUrl, PHP_URL_SCHEME) !== 'https' || parse_url($searchUrl, PHP_URL_HOST) === null || $userAgent === '') {
            return ['status' => 'unavailable', 'results' => []];
        }

        $normalizedQuery = trim(preg_replace('/\s+/u', ' ', $query) ?? $query);
        $cacheKey = 'nominatim:search:'.hash('sha256', mb_strtolower($normalizedQuery));

        try {
            $cache = Cache::store(config('services.nominatim.cache_store', 'database'));
            $cachedResults = $cache->get($cacheKey);

            if (is_array($cachedResults)) {
                return ['status' => 'ok', 'results' => $cachedResults];
            }

            $lock = $cache->lock('nominatim:global-request-lock', self::LOCK_SECONDS);
            $result = $lock->get(function () use ($cache, $cacheKey, $normalizedQuery): array {
                $cachedResults = $cache->get($cacheKey);

                if (is_array($cachedResults)) {
                    return ['status' => 'ok', 'results' => $cachedResults];
                }

                if (! $cache->add('nominatim:global-cooldown', true, self::GLOBAL_COOLDOWN_SECONDS)) {
                    return ['status' => 'limited', 'results' => []];
                }

                return $this->requestResults($cache, $cacheKey, $normalizedQuery);
            });

            return is_array($result)
                ? $result
                : ['status' => 'limited', 'results' => []];
        } catch (Throwable) {
            return ['status' => 'unavailable', 'results' => []];
        }
    }

    /**
     * @return array{status: 'ok'|'unavailable', results: list<array<string, string>>}
     */
    private function requestResults(mixed $cache, string $cacheKey, string $query): array
    {
        $userAgent = trim((string) config('services.nominatim.user_agent'));

        if ($userAgent === '') {
            return ['status' => 'unavailable', 'results' => []];
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(5)
                ->withoutRedirecting()
                ->withHeaders([
                    'User-Agent' => $userAgent,
                    'Accept-Language' => 'es',
                ])
                ->acceptJson()
                ->get((string) config('services.nominatim.search_url'), [
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'limit' => 5,
                    'q' => $query,
                ]);

            if (! $response->successful()) {
                return ['status' => 'unavailable', 'results' => []];
            }

            $payload = $response->json();

            if (! is_array($payload) || ! array_is_list($payload)) {
                return ['status' => 'unavailable', 'results' => []];
            }

            $results = [];

            foreach (array_slice($payload, 0, 5) as $item) {
                if (! is_array($item) || ! is_array($item['address'] ?? null)) {
                    return ['status' => 'unavailable', 'results' => []];
                }

                $latitude = $this->coordinate($item['lat'] ?? null, -90, 90);
                $longitude = $this->coordinate($item['lon'] ?? null, -180, 180);
                $label = $this->safeText($item['display_name'] ?? null, 300);

                if ($latitude === null || $longitude === null || $label === '') {
                    return ['status' => 'unavailable', 'results' => []];
                }

                $address = $item['address'];
                $street = trim(implode(' ', array_filter([
                    $this->safeText($address['road'] ?? null, 150),
                    $this->safeText($address['house_number'] ?? null, 30),
                ], fn (string $part): bool => $part !== '')));

                $results[] = [
                    'label' => $label,
                    'country_code' => strtoupper($this->safeText($address['country_code'] ?? null, 2)),
                    'region' => $this->safeText($address['state'] ?? $address['province'] ?? null, 255),
                    'city' => $this->safeText($address['city'] ?? $address['town'] ?? $address['village'] ?? $address['municipality'] ?? null, 255),
                    'street_address' => $street,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ];
            }

            $cache->put($cacheKey, $results, self::CACHE_SECONDS);

            return ['status' => 'ok', 'results' => $results];
        } catch (Throwable) {
            return ['status' => 'unavailable', 'results' => []];
        }
    }

    private function coordinate(mixed $value, float $minimum, float $maximum): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < $minimum || (float) $value > $maximum) {
            return null;
        }

        return number_format((float) $value, 7, '.', '');
    }

    private function safeText(mixed $value, int $maximumLength): string
    {
        if (! is_string($value)) {
            return '';
        }

        $text = strip_tags(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? '');

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $text) ?? ''), 0, $maximumLength);
    }
}
