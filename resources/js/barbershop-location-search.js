const LOCATION_FIELDS = ['country_code', 'region', 'city', 'street_address', 'latitude', 'longitude'];
const MANUAL_FALLBACK = 'No fue posible realizar la búsqueda. Puedes completar la ubicación manualmente.';

export function initializeBarbershopLocationSearch(document, fetchImplementation = (...args) => fetch(...args)) {
    document.querySelectorAll('[data-barbershop-location-search]').forEach((root) => {
        const queryInput = root.querySelector('[data-location-search-query]');
        const searchButton = root.querySelector('[data-location-search-button]');
        const status = root.querySelector('[data-location-search-status]');
        const resultsList = root.querySelector('[data-location-search-results]');
        const confirmButton = root.querySelector('[data-location-search-confirm]');
        let requestId = 0;
        let selectedResult = null;
        let selectedForQuery = '';

        if (!queryInput || !searchButton || !status || !resultsList || !confirmButton) {
            return;
        }

        function clearResults() {
            resultsList.replaceChildren();
            selectedResult = null;
            selectedForQuery = '';
            confirmButton.disabled = true;
        }

        queryInput.addEventListener('input', () => {
            requestId++;
            clearResults();
            status.textContent = '';
            searchButton.disabled = false;
        });

        searchButton.addEventListener('click', async (event) => {
            event.preventDefault();
            if (searchButton.disabled) {
                return;
            }
            const query = queryInput.value.trim();

            if (query.length < 4 || query.length > 255) {
                clearResults();
                status.textContent = 'Escribe una dirección comercial de entre 4 y 255 caracteres.';
                return;
            }

            const currentRequestId = ++requestId;
            clearResults();
            searchButton.disabled = true;
            status.textContent = 'Buscando…';

            try {
                const payload = { q: query };
                if (root.dataset.barbershopSlug) {
                    payload.barbershop_slug = root.dataset.barbershopSlug;
                }
                const response = await fetchImplementation(root.dataset.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': root.dataset.csrfToken,
                    },
                    body: JSON.stringify(payload),
                });
                const data = await response.json();

                if (currentRequestId !== requestId || queryInput.value.trim() !== query) {
                    return;
                }

                if (!response.ok || !Array.isArray(data.results)) {
                    status.textContent = MANUAL_FALLBACK;
                    return;
                }

                if (data.results.length === 0) {
                    status.textContent = 'No se encontraron resultados. Puedes completar la ubicación manualmente.';
                    return;
                }

                for (const result of data.results.slice(0, 5)) {
                    if (!result || typeof result.label !== 'string') {
                        continue;
                    }
                    const item = document.createElement('li');
                    const choice = document.createElement('button');
                    choice.type = 'button';
                    choice.textContent = result.label;
                    choice.setAttribute('aria-pressed', 'false');
                    choice.addEventListener('click', () => {
                        if (currentRequestId !== requestId || queryInput.value.trim() !== query) {
                            return;
                        }
                        selectedResult = result;
                        selectedForQuery = query;
                        Array.from(resultsList.children).forEach((child) => child.children[0].setAttribute('aria-pressed', 'false'));
                        choice.setAttribute('aria-pressed', 'true');
                        confirmButton.disabled = false;
                        status.textContent = 'Resultado seleccionado. Confirma para copiarlo a los campos editables.';
                    });
                    item.append(choice);
                    resultsList.append(item);
                }

                status.textContent = resultsList.children.length > 0
                    ? 'Selecciona el resultado que corresponda y confirma la ubicación.'
                    : MANUAL_FALLBACK;
            } catch {
                if (currentRequestId === requestId) {
                    status.textContent = MANUAL_FALLBACK;
                }
            } finally {
                if (currentRequestId === requestId) {
                    searchButton.disabled = false;
                }
            }
        });

        confirmButton.addEventListener('click', (event) => {
            event.preventDefault();
            const query = queryInput.value.trim();
            if (!selectedResult || selectedForQuery !== query || !query) {
                clearResults();
                status.textContent = 'Vuelve a buscar y selecciona un resultado vigente antes de confirmar.';
                return;
            }

            for (const name of LOCATION_FIELDS) {
                const input = root.querySelector(`[name="${name}"]`);
                if (!input) {
                    continue;
                }
                input.value = typeof selectedResult[name] === 'string' ? selectedResult[name] : '';
                const EventConstructor = document.defaultView?.Event ?? globalThis.Event;
                if (EventConstructor) {
                    input.dispatchEvent(new EventConstructor('input', { bubbles: true }));
                    input.dispatchEvent(new EventConstructor('change', { bubbles: true }));
                }
            }

            status.textContent = 'Ubicación copiada a los campos. Revisa los datos y guarda para conservarlos.';
        });
    });
}
