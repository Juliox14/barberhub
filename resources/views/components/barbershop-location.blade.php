@php
    $hasAddress = collect([$barbershop->country_code, $barbershop->region, $barbershop->city, $barbershop->street_address])->contains(fn ($value) => filled($value));
    $latitude = $barbershop->latitude;
    $longitude = $barbershop->longitude;
    $hasCoordinates = is_numeric($latitude) && is_numeric($longitude)
        && (float) $latitude >= -90 && (float) $latitude <= 90
        && (float) $longitude >= -180 && (float) $longitude <= 180;
    $coordinatePair = $hasCoordinates ? $latitude.','.$longitude : null;
    $embedUrl = \App\Support\GoogleMapsEmbed::canonicalUrl($barbershop->google_maps_embed_url);
@endphp
@if ($hasAddress || $hasCoordinates || $embedUrl !== null)
    <section class="bh-public-panel bh-public-location" aria-labelledby="barbershop-location-heading">
        <h2 id="barbershop-location-heading">Dónde estamos</h2>
        @if ($hasAddress)
            <address>
                {{ collect([$barbershop->street_address, $barbershop->city, $barbershop->region, \App\Support\CountryNames::name($barbershop->country_code)])->filter()->implode(', ') }}
            </address>
        @endif
        @if ($hasCoordinates)
            <p><a class="bh-public-link" href="https://www.google.com/maps/dir/?api=1&amp;destination={{ $coordinatePair }}" target="_blank" rel="noopener noreferrer">Cómo llegar con Google Maps</a></p>
        @endif
        @if ($embedUrl !== null)
            <iframe title="Mapa de Google Maps" loading="lazy" referrerpolicy="no-referrer" width="600" height="350" class="bh-public-location__map" src="{{ $embedUrl }}"></iframe>
        @elseif ($hasCoordinates)
            <iframe title="Mapa de ubicación de {{ $barbershop->name }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" width="600" height="350" class="bh-public-location__map" src="https://maps.google.com/maps?q={{ $coordinatePair }}&amp;z=16&amp;output=embed"></iframe>
        @endif
    </section>
@endif
