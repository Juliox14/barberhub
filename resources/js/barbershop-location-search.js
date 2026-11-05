const SDK_URL = 'https://maps.googleapis.com/maps/api/js';
const SDK_TIMEOUT_MS = 15000;
const SDK_PROMISES = new WeakMap();
let callbackSequence = 0;
const FALLBACK = 'No se pudo cargar el autocompletado. Revisa la configuración de Google o completa la ubicación manualmente.';
const MISSING_KEY = 'El autocompletado de Google no está configurado. Contacta al administrador o completa la ubicación manualmente.';
const ADDRESS_FIELDS = ['country_code', 'region', 'city', 'street_address'];
const LOCATION_FIELDS = [...ADDRESS_FIELDS, 'latitude', 'longitude'];

function getMaps(window) {
    return window.google?.maps;
}

function loadGoogleMaps(document, window, apiKey) {
    const existingMaps = getMaps(window);
    if (existingMaps?.importLibrary) {
        return Promise.resolve(existingMaps);
    }
    const existing = SDK_PROMISES.get(document);
    if (existing) {
        return existing;
    }

    const promise = new Promise((resolve, reject) => {
        let callbackName;
        do {
            callbackName = `__barbershopPlacesReady${++callbackSequence}`;
        } while (callbackName in window);
        const script = document.createElement('script');
        const timeout = window.setTimeout(() => finish(new Error('Google Maps loading timed out.')), SDK_TIMEOUT_MS);
        let settled = false;
        const previousCallback = window[callbackName];

        function cleanup() {
            window.clearTimeout(timeout);
            if (window[callbackName] === callback) {
                if (previousCallback === undefined) {
                    delete window[callbackName];
                } else {
                    window[callbackName] = previousCallback;
                }
            }
            script.onerror = null;
        }

        function finish(error) {
            if (settled) {
                return;
            }
            settled = true;
            cleanup();
            const maps = getMaps(window);
            if (error || !maps?.importLibrary) {
                SDK_PROMISES.delete(document);
                reject(error ?? new Error('Google Maps did not initialize.'));
                return;
            }
            resolve(maps);
        }

        function callback() {
            finish();
        }

        window[callbackName] = callback;
        const parameters = new URLSearchParams({ key: apiKey, language: 'es', v: 'weekly', loading: 'async', callback: callbackName });
        script.src = `${SDK_URL}?${parameters.toString()}`;
        script.async = true;
        script.onerror = () => finish(new Error('Google Maps script failed to load.'));
        document.head.append(script);
    });
    SDK_PROMISES.set(document, promise);
    return promise;
}

function component(components, type) {
    return components.find((item) => item.types?.includes(type));
}

function addressFrom(place) {
    if (!Array.isArray(place.addressComponents) || place.addressComponents.length === 0) {
        return null;
    }
    const country = component(place.addressComponents, 'country');
    const city = component(place.addressComponents, 'locality')
        ?? component(place.addressComponents, 'postal_town')
        ?? component(place.addressComponents, 'administrative_area_level_2');
    const street = [
        component(place.addressComponents, 'route')?.longText,
        component(place.addressComponents, 'street_number')?.longText,
        component(place.addressComponents, 'subpremise')?.longText,
    ].filter(Boolean).join(' ');
    const address = {
        country_code: country?.shortText?.toUpperCase() ?? '',
        region: component(place.addressComponents, 'administrative_area_level_1')?.longText ?? '',
        city: city?.longText ?? '',
        street_address: street,
    };
    return Object.values(address).some(Boolean) ? address : null;
}

function initializeRoot(root, document, window) {
    const container = root.querySelector('[data-location-search-widget]');
    const status = root.querySelector('[data-location-search-status]');
    const inputs = Object.fromEntries(LOCATION_FIELDS.map((name) => [name, root.querySelector(`[name="${name}"]`)]));
    if (!container || !status || LOCATION_FIELDS.some((name) => !inputs[name])) {
        return Promise.resolve();
    }

    const apiKey = root.dataset.apiKey;
    if (!apiKey) {
        status.textContent = MISSING_KEY;
        return Promise.resolve();
    }

    let selection = 0;
    let manualEdit = 0;
    let applying = false;
    for (const input of Object.values(inputs)) {
        input.addEventListener('input', () => { if (!applying) { manualEdit++; } });
        input.addEventListener('change', () => { if (!applying) { manualEdit++; } });
    }

    return loadGoogleMaps(document, window, apiKey)
        .then(async (maps) => {
            const { PlaceAutocompleteElement } = await maps.importLibrary('places');
            const widget = new PlaceAutocompleteElement();
            widget.setAttribute('aria-label', 'Buscar dirección');
            widget.setAttribute('placeholder', 'Escribe una dirección');
            widget.addEventListener('gmp-error', () => { status.textContent = FALLBACK; });
            widget.addEventListener('gmp-select', async ({ placePrediction }) => {
                const currentSelection = ++selection;
                const editAtSelection = manualEdit;
                status.textContent = 'Cargando los datos de la dirección seleccionada…';
                try {
                    const selectedPlace = placePrediction.toPlace();
                    await selectedPlace.fetchFields({ fields: ['addressComponents'] });
                    if (currentSelection !== selection) {
                        return;
                    }
                    if (editAtSelection !== manualEdit) {
                        status.textContent = 'No se aplicó el resultado porque editaste los campos manualmente. Puedes continuar con tus cambios.';
                        return;
                    }
                    const address = addressFrom(selectedPlace);
                    if (!address) {
                        status.textContent = 'No fue posible obtener los componentes de la dirección. Puedes completarla manualmente.';
                        return;
                    }
                    applying = true;
                    try {
                        for (const name of ADDRESS_FIELDS) {
                            inputs[name].value = address[name];
                            const EventConstructor = document.defaultView?.Event ?? globalThis.Event;
                            if (EventConstructor) {
                                inputs[name].dispatchEvent(new EventConstructor('input', { bubbles: true }));
                                inputs[name].dispatchEvent(new EventConstructor('change', { bubbles: true }));
                            }
                        }
                        for (const name of ['latitude', 'longitude']) {
                            inputs[name].value = '';
                            const EventConstructor = document.defaultView?.Event ?? globalThis.Event;
                            if (EventConstructor) {
                                inputs[name].dispatchEvent(new EventConstructor('input', { bubbles: true }));
                                inputs[name].dispatchEvent(new EventConstructor('change', { bubbles: true }));
                            }
                        }
                    } finally {
                        applying = false;
                    }
                    status.textContent = 'Dirección completada. Revisa y edita los datos; introduce coordenadas manuales válidas y guarda para conservarlos.';
                } catch {
                    if (currentSelection === selection) {
                        status.textContent = FALLBACK;
                    }
                }
            });
            container.append(widget);
        })
        .catch(() => { status.textContent = FALLBACK; });
}

export function initializeBarbershopLocationSearch(document) {
    const window = document.defaultView ?? globalThis.window;
    if (!window) {
        return Promise.resolve();
    }
    return Promise.all(Array.from(document.querySelectorAll('[data-barbershop-location-search]'), (root) => initializeRoot(root, document, window)));
}
