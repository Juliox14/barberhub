<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use Throwable;

final class GoogleMapsEmbed
{
    public const VALIDATION_MESSAGE = 'Pega una URL de inserción de Google Maps válida o el HTML iframe copiado desde Compartir > Insertar un mapa.';

    private const MAX_INPUT_LENGTH = 10000;

    public static function canonicalUrl(mixed $input): ?string
    {
        if (! is_string($input)) {
            return null;
        }

        $input = trim($input);
        if ($input === '') {
            return null;
        }

        if (strlen($input) > self::MAX_INPUT_LENGTH) {
            return null;
        }

        if (preg_match('/<!\s*DOCTYPE/i', $input)) {
            return null;
        }

        $url = str_starts_with(strtolower($input), '<')
            ? self::iframeSource($input)
            : $input;

        return $url === null ? null : self::canonicalizeUrl($url);
    }

    private static function iframeSource(string $html): ?string
    {
        $document = new DOMDocument;
        $previousErrorMode = libxml_use_internal_errors(true);

        try {
            if (! $document->loadHTML($html, LIBXML_NONET | LIBXML_HTML_NODEFDTD)) {
                return null;
            }

            $iframes = $document->getElementsByTagName('iframe');
            if ($iframes->length !== 1) {
                return null;
            }

            $iframe = $iframes->item(0);
            if (! $iframe instanceof DOMElement || ! $iframe->hasAttribute('src')) {
                return null;
            }

            return $iframe->getAttribute('src');
        } catch (Throwable) {
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }

    private static function canonicalizeUrl(string $url): ?string
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts)
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || strtolower($parts['host'] ?? '') !== 'www.google.com'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
            || isset($parts['fragment'])
            || ($parts['path'] ?? null) !== '/maps/embed'
            || ! isset($parts['query'])) {
            return null;
        }

        parse_str($parts['query'], $query);
        if (! isset($query['pb']) || ! is_string($query['pb']) || trim($query['pb']) === '') {
            return null;
        }

        return 'https://www.google.com/maps/embed?'.$parts['query'];
    }
}
