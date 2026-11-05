@php($location = $barbershop ?? null)
@php($countryCodeValue = old('country_code', $location?->country_code))
@php($selectedCountryCode = is_string($countryCodeValue) ? strtoupper(trim($countryCodeValue)) : '')
<section class="sm:col-span-2 space-y-4" aria-labelledby="location-heading">
    <div>
        <h3 id="location-heading" class="text-sm font-semibold">Ubicación (opcional)</h3>
        <p class="mt-1 text-xs leading-5 text-slate-500">Completa manualmente el país, estado o provincia, ciudad y dirección. Para copiar la dirección exacta, <a href="https://www.google.com/maps" target="_blank" rel="noopener noreferrer">Abrir Google Maps</a>, busca el local y copia la dirección. Guarda el formulario normalmente; las coordenadas siguen siendo opcionales y se capturan manualmente.</p>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="country_code" class="block text-sm font-medium">País (opcional)</label>
            <select id="country_code" name="country_code" class="mt-1 block w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-2 text-sm text-white">
                <option value="" @selected($selectedCountryCode === '')>Seleccionar país (opcional)</option>
                @foreach (\App\Support\CountryNames::all() as $countryCode => $countryName)
                    <option value="{{ $countryCode }}" @selected($selectedCountryCode === $countryCode)>{{ $countryName }}</option>
                @endforeach
            </select>
            @error('country_code')<p class="mt-1 text-sm text-rose-300">{{ $message }}</p>@enderror
        </div>
        @foreach ([['region', 'Estado o provincia'], ['city', 'Ciudad o localidad'], ['street_address', 'Dirección']] as [$field, $label])
            <div class="{{ $field === 'street_address' ? 'sm:col-span-2' : '' }}"><label for="{{ $field }}" class="block text-sm font-medium">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $location?->{$field}) }}" maxlength="{{ $field === 'street_address' ? 1000 : 255 }}" class="mt-1 block w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-2 text-sm text-white">@error($field)<p class="mt-1 text-sm text-rose-300">{{ $message }}</p>@enderror</div>
        @endforeach
        @foreach ([['latitude', 'Latitud', '-90', '90'], ['longitude', 'Longitud', '-180', '180']] as [$field, $label, $min, $max])
            <div><label for="{{ $field }}" class="block text-sm font-medium">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="number" step="0.0000001" min="{{ $min }}" max="{{ $max }}" value="{{ old($field, $location?->{$field}) }}" class="mt-1 block w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-2 text-sm text-white">@error($field)<p class="mt-1 text-sm text-rose-300">{{ $message }}</p>@enderror</div>
        @endforeach
    </div>
</section>
