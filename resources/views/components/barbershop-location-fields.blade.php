@php($location = $barbershop ?? null)
<section class="sm:col-span-2 space-y-4" aria-labelledby="location-heading">
    <div><h3 id="location-heading" class="text-sm font-semibold">Ubicación (opcional)</h3><p class="mt-1 text-xs leading-5 text-slate-500">Escribe el código de país de dos letras (ISO 3166-1, por ejemplo MX o CO), estado o provincia, ciudad y dirección. Para el mapa, abre Google Maps, mantén pulsado o haz clic derecho sobre el punto y copia las coordenadas decimales. No se usa API key ni hay cargos.</p></div>
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ([['country_code', 'País (código de dos letras)'], ['region', 'Estado o provincia'], ['city', 'Ciudad o localidad'], ['street_address', 'Dirección']] as [$field, $label])
            <div class="{{ $field === 'street_address' ? 'sm:col-span-2' : '' }}"><label for="{{ $field }}" class="block text-sm font-medium">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $location?->{$field}) }}" maxlength="{{ $field === 'street_address' ? 1000 : ($field === 'country_code' ? 2 : 255) }}" class="mt-1 block w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-2 text-sm text-white" @if ($field === 'country_code') autocomplete="country" @endif>@error($field)<p class="mt-1 text-sm text-rose-300">{{ $message }}</p>@enderror</div>
        @endforeach
        @foreach ([['latitude', 'Latitud', '-90', '90'], ['longitude', 'Longitud', '-180', '180']] as [$field, $label, $min, $max])
            <div><label for="{{ $field }}" class="block text-sm font-medium">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="number" step="0.0000001" min="{{ $min }}" max="{{ $max }}" value="{{ old($field, $location?->{$field}) }}" class="mt-1 block w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-2 text-sm text-white">@error($field)<p class="mt-1 text-sm text-rose-300">{{ $message }}</p>@enderror</div>
        @endforeach
    </div>
    <p class="text-xs leading-5 text-slate-500">Las coordenadas deben indicarse juntas. Sin coordenadas, se mostrará la dirección sin mapa.</p>
</section>
