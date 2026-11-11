import { initializeBookingWizard } from './public-booking-wizard.js';

export async function requestVerification(url, payload, csrfToken, fetchImplementation = fetch) {
    const response = await fetchImplementation(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify(payload),
    });
    const result = await response.json();
    if (!response.ok) {
        const message = Object.values(result.errors ?? {}).flat().join(' ');
        throw new Error(message || 'Código incorrecto o vencido. Revisa el código o solicita uno nuevo.');
    }
    return result;
}

export function createAvailabilityLoader({ onState, fetchImplementation = fetch }) {
    let revision = 0;
    let controller;
    const load = async (url, filters) => {
        const current = ++revision;
        controller?.abort();
        controller = new AbortController();
        const requestController = controller;
        const signal = requestController.signal;
        const timeout = setTimeout(() => requestController.abort(), 10000);
        onState({ status: 'loading', slots: [] });
        const query = new URLSearchParams(Object.entries(filters).filter(([, value]) => value !== '' && value != null));
        try {
            const response = await fetchImplementation(`${url}?${query}`, {
                credentials: 'same-origin', headers: { Accept: 'application/json' }, signal,
            });
            if (!response.ok) throw new Error('No pudimos consultar los horarios. Intenta nuevamente.');
            const result = await response.json();
            if (!Array.isArray(result.slots)) throw new Error('La respuesta de horarios no es válida. Intenta nuevamente.');
            if (current !== revision) return;
            onState({ status: result.slots.length ? 'ready' : 'empty', slots: result.slots });
        } catch (error) {
            if (current === revision) onState({ status: 'error', slots: [], message: signal.aborted ? 'La consulta tardó demasiado. Intenta nuevamente.' : error.message });
        } finally {
            clearTimeout(timeout);
        }
    };
    load.cancel = () => {
        revision++;
        controller?.abort();
        onState({ status: 'idle', slots: [] });
    };
    return load;
}

function updateBookingSubmit(form) {
    const submit = form.querySelector('[data-slot-submit]');
    if (submit) submit.disabled = form.dataset.emailVerified !== 'true' || form.dataset.availabilityReady !== 'true';
}

function initializeAvailability(document, fetchImplementation) {
    document.querySelectorAll('[data-availability]').forEach((filters) => {
        const form = document.querySelector('[data-public-booking]');
        const list = document.querySelector('[data-slot-list]');
        const status = document.querySelector('[data-slot-status]');
        const selected = document.querySelector('[data-selected-slot]');
        const professional = filters.querySelector('[name="barber_id"]');
        if (!form || !list || !status) return;
        const fieldset = filters.querySelector('[data-slot-fieldset]') ?? form.querySelector('[data-slot-fieldset]');
        const dateField = filters.querySelector('[name="date"]');
        const dates = filters.querySelector('[data-date-options]');
        const picker = filters.querySelector('[data-date-picker]');
        let firstDate = dateField.value;
        const renderDates = () => {
            if (!dates) return;
            dates.replaceChildren();
            const value = dateField.value;
            if (!value || value < dateField.min || value > dateField.max) {
                dates.hidden = true;
                if (picker) picker.open = true;
                return;
            }
            const last = new Date(`${firstDate}T12:00:00Z`);
            last.setUTCDate(last.getUTCDate() + 4);
            if (!firstDate || value < firstDate || value > last.toISOString().slice(0, 10)) firstDate = value;
            for (let offset = 0; offset < 5; offset++) {
                const day = new Date(`${firstDate}T12:00:00Z`);
                day.setUTCDate(day.getUTCDate() + offset);
                const iso = day.toISOString().slice(0, 10);
                if (iso < dateField.min || iso > dateField.max) continue;
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'bh-public-date';
                button.dataset.date = iso;
                button.setAttribute('aria-pressed', String(iso === value));
                button.setAttribute('aria-label', new Intl.DateTimeFormat('es', { dateStyle: 'full', timeZone: 'UTC' }).format(day));
                const weekday = document.createElement('span');
                weekday.textContent = new Intl.DateTimeFormat('es', { weekday: 'short', timeZone: 'UTC' }).format(day).replace('.', '');
                const number = document.createElement('strong');
                number.textContent = iso.slice(-2);
                button.append(weekday, number);
                button.addEventListener('click', () => {
                    dateField.value = iso;
                    if (picker) picker.open = false;
                    return refresh();
                });
                dates.append(button);
            }
            dates.hidden = false;
        };
        const updatePreview = (choice = null) => {
            const service = filters.querySelector('[name="service_id"]:checked');
            const date = filters.querySelector('[name="date"]').value;
            const time = choice?.querySelector('strong')?.textContent;
            const barber = choice?.querySelector('small')?.textContent;
            const preview = {
                service: service?.dataset.serviceName ?? 'Elige un servicio',
                duration: service ? `${service.dataset.serviceDuration} min` : '—',
                price: service?.dataset.servicePrice ?? '—',
                date: date ? date.split('-').reverse().join('/') : 'Elige una fecha',
                time: time ?? 'Elige un horario',
                barber: barber ?? 'Profesional pendiente',
            };
            Object.entries(preview).forEach(([key, value]) => {
                document.querySelectorAll(`[data-booking-${key}]`).forEach((node) => { node.textContent = value; });
            });
        };
        updatePreview(list.querySelector('input:checked')?.closest('label'));
        const load = createAvailabilityLoader({ fetchImplementation, onState: (state) => {
            filters.dataset.availabilityState = state.status;
            list.setAttribute('aria-busy', String(state.status === 'loading'));
            list.replaceChildren();
            if (fieldset) fieldset.disabled = state.status !== 'ready';
            form.dataset.availabilityReady = String(state.status === 'ready');
            updateBookingSubmit(form);
            updatePreview();
            if (selected) selected.textContent = 'Selecciona un horario para ver tu elección.';
            status.textContent = {
                idle: 'Completa el servicio y la fecha para consultar horarios.',
                loading: 'Buscando horarios disponibles…',
                ready: `${state.slots.length} horarios disponibles. Elige el que mejor te quede.`,
                empty: 'No hay horarios para esta selección. Prueba otra fecha o elige otro profesional.',
                error: state.message ?? 'No pudimos consultar los horarios. Intenta nuevamente.',
            }[state.status];
            state.slots.forEach((slot) => {
                const label = document.createElement('label');
                label.className = 'bh-public-choice';
                const input = document.createElement('input');
                input.type = 'radio';
                input.name = 'slot';
                input.value = `${slot.barber_id}|${slot.starts_at}`;
                input.required = true;
                input.setAttribute('form', 'public-booking-form');
                const details = document.createElement('span');
                const time = document.createElement('strong');
                time.textContent = slot.starts_at.slice(11);
                const barber = document.createElement('small');
                barber.textContent = slot.barber_name;
                details.append(time, barber);
                label.append(input, details);
                list.append(label);
            });
        } });
        const refresh = () => {
            renderDates();
            const service = filters.querySelector('[name="service_id"]:checked');
            if (!service || !filters.reportValidity()) {
                form.querySelector('[name="service_id"]').value = '';
                load.cancel();
                return;
            }
            form.querySelector('[name="service_id"]').value = service.value;
            return load(filters.dataset.availabilityUrl, {
                service_id: service.value,
                barber_id: professional.value,
                date: filters.querySelector('[name="date"]').value,
            });
        };
        let servicesRevision = 0;
        let servicesController;
        const refreshServices = async () => {
            const current = ++servicesRevision;
            servicesController?.abort();
            servicesController = undefined;
            const serviceList = filters.querySelector('[data-service-list]');
            const serviceFieldset = filters.querySelector('[data-service-fieldset]');
            const serviceStatus = filters.querySelector('[data-service-status]');
            const servicePanel = filters.querySelector('[data-step-panel="2"]');
            const slotPanel = filters.querySelector('[data-step-panel="3"]');
            const serviceInput = form.querySelector('[name="service_id"]');
            const barberId = professional.value;
            if (serviceList) serviceList.replaceChildren();
            if (serviceFieldset) serviceFieldset.disabled = true;
            if (serviceInput) serviceInput.value = '';
            if (servicePanel) servicePanel.hidden = !barberId;
            if (slotPanel) slotPanel.hidden = !barberId;
            dateField.value = dateField.min;
            load.cancel();
            updatePreview();
            if (!barberId) {
                if (serviceStatus) {
                    serviceStatus.hidden = false;
                    serviceStatus.textContent = 'Selecciona un profesional para ver sus servicios.';
                }
                return;
            }
            if (serviceStatus) {
                serviceStatus.hidden = false;
                serviceStatus.textContent = 'Cargando servicios disponibles…';
            }
            const requestController = new AbortController();
            servicesController = requestController;
            try {
                const query = new URLSearchParams({ barber_id: barberId });
                const response = await fetchImplementation(`${filters.dataset.bookingUrl}?${query}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                    signal: requestController.signal,
                });
                if (!response.ok) throw new Error('No pudimos consultar los servicios. Intenta nuevamente.');
                const result = await response.json();
                if (!Array.isArray(result.services)) throw new Error('La respuesta de servicios no es válida. Intenta nuevamente.');
                if (current !== servicesRevision) return;
                result.services.forEach((service) => {
                    const label = document.createElement('label');
                    label.className = 'bh-public-choice bh-public-service';
                    const input = document.createElement('input');
                    input.type = 'radio';
                    input.name = 'service_id';
                    input.value = String(service.id);
                    input.required = true;
                    input.dataset.serviceName = service.name;
                    input.dataset.servicePrice = service.price;
                    input.dataset.serviceDuration = String(service.duration_minutes);
                    const details = document.createElement('span');
                    details.className = 'bh-public-service__details';
                    const name = document.createElement('strong');
                    name.textContent = service.name;
                    details.append(name);
                    if (service.description) {
                        const description = document.createElement('small');
                        description.textContent = service.description;
                        details.append(description);
                    }
                    const duration = document.createElement('small');
                    duration.textContent = `${service.duration_minutes} min`;
                    details.append(duration);
                    const price = document.createElement('span');
                    price.className = 'bh-public-service__price';
                    price.textContent = service.price;
                    label.append(input, details, price);
                    serviceList.append(label);
                });
                if (serviceFieldset) serviceFieldset.disabled = result.services.length === 0;
                if (serviceStatus) {
                    serviceStatus.hidden = result.services.length > 0;
                    serviceStatus.textContent = 'Este profesional aún no tiene servicios asignados.';
                }
            } catch (error) {
                if (current !== servicesRevision || requestController.signal.aborted) return;
                if (serviceStatus) {
                    serviceStatus.hidden = false;
                    serviceStatus.textContent = error.message || 'No pudimos consultar los servicios. Intenta nuevamente.';
                }
            }
        };
        professional?.addEventListener('change', refreshServices);
        renderDates();
        if (dates && !dates.hidden && picker) picker.open = false;
        filters.dataset.enhanced = 'true';
        filters.addEventListener('submit', (event) => { event.preventDefault(); return refresh(); });
        filters.addEventListener('change', (event) => {
            if (event.target.name === 'barber_id') return;
            if (['service_id', 'date'].includes(event.target.name)) return refresh();
        });
        list.addEventListener('change', (event) => {
            if (event.target.name !== 'slot') return;
            const choice = event.target.closest?.('label');
            const time = choice?.querySelector('strong')?.textContent;
            const barber = choice?.querySelector('small')?.textContent;
            if (selected && time && barber) selected.textContent = `${time} con ${barber}`;
            updatePreview(choice);
        });
    });
}

export function initializePublicBooking(document, fetchImplementation = fetch, timers = {}) {
    initializeAvailability(document, fetchImplementation);
    initializeBookingWizard(document);
    const now = timers.now ?? Date.now;
    const setIntervalImplementation = timers.setInterval ?? setInterval;
    const clearIntervalImplementation = timers.clearInterval ?? clearInterval;
    const cooldowns = new Map();
    const issuedEmails = new Set();
    document.querySelectorAll('[data-public-booking]').forEach((form) => {
        updateBookingSubmit(form);
        const issue = form.querySelector('[data-issue-code]');
        const verify = form.querySelector('[data-verify-code]');
        const status = form.querySelector('[data-code-status]');
        const resendStatus = form.querySelector('[data-resend-status]');
        const email = form.querySelector('[name="email"]');
        const code = form.querySelector('[data-email-code]');
        const controls = form.querySelector('[data-verification-controls]');
        const codeControls = form.querySelector('[data-code-controls]');
        if (!issue || !verify || !status || !resendStatus || !email || !code || !controls || !codeControls) return;
        const normalizeEmail = (value) => value.trim().toLowerCase();
        let currentEmail = normalizeEmail(email.value);
        let revision = 0;
        let pending = false;
        let cooldownTimer;
        const csrf = form.querySelector('[name="_token"]').value;
        const stopCooldownTimer = () => {
            if (cooldownTimer === undefined) return;
            clearIntervalImplementation(cooldownTimer);
            cooldownTimer = undefined;
        };
        const ensureCooldownTimer = () => {
            if (cooldownTimer !== undefined || cooldowns.size === 0) return;
            cooldownTimer = setIntervalImplementation(refreshCooldown, 1000);
            cooldownTimer?.unref?.();
        };
        const refreshCooldown = () => {
            const currentTime = now();
            for (const [emailAddress, expiresAt] of cooldowns) {
                if (expiresAt <= currentTime) cooldowns.delete(emailAddress);
            }
            const remaining = Math.max(0, (cooldowns.get(currentEmail) ?? 0) - currentTime);
            issue.disabled = pending || remaining > 0;
            issue.textContent = issuedEmails.has(currentEmail) ? 'Reenviar código' : 'Enviar código';
            resendStatus.textContent = remaining > 0
                ? `Puedes solicitar otro código en ${Math.ceil(remaining / 1000)} segundos.`
                : '';
            if (cooldowns.size === 0) stopCooldownTimer();
        };
        const startCooldown = () => {
            issuedEmails.add(currentEmail);
            cooldowns.set(currentEmail, now() + 300000);
            ensureCooldownTimer();
            refreshCooldown();
        };
        const perform = async (url, payload, successMessage, confirmsEmail = false) => {
            if (pending || (confirmsEmail && codeControls.hidden) || !email.reportValidity()) return;
            const requestRevision = ++revision;
            const submittedEmail = normalizeEmail(payload.email);
            const isCurrent = () => requestRevision === revision && submittedEmail === normalizeEmail(email.value);
            pending = true;
            form.dataset.emailVerified = 'false';
            updateBookingSubmit(form);
            issue.disabled = true;
            verify.disabled = true;
            status.textContent = 'Procesando…';
            try {
                const result = await requestVerification(url, payload, csrf, fetchImplementation);
                if (!isCurrent()) return;
                if (confirmsEmail && result.verified !== true) {
                    throw new Error('No pudimos verificar el correo. Revisa el código o solicita uno nuevo.');
                }
                status.textContent = result.status ?? successMessage;
                if (confirmsEmail) {
                    form.dataset.emailVerified = 'true';
                    updateBookingSubmit(form);
                    controls.hidden = true;
                    codeControls.hidden = true;
                    code.value = '';
                    code.disabled = true;
                    verify.disabled = true;
                    stopCooldownTimer();
                } else {
                    codeControls.hidden = false;
                    code.disabled = false;
                    verify.disabled = false;
                    startCooldown();
                }
            } catch (error) {
                if (isCurrent()) status.textContent = error.message || 'No pudimos conectarnos. Intenta nuevamente.';
            } finally {
                if (requestRevision === revision) {
                    pending = false;
                    issue.disabled = cooldowns.has(currentEmail) && cooldowns.get(currentEmail) > now();
                    if (!codeControls.hidden && form.dataset.emailVerified !== 'true') verify.disabled = false;
                }
            }
        };
        issue.addEventListener('click', () => perform(form.dataset.issueUrl, { email: email.value }, 'Revisa tu correo.'));
        verify.addEventListener('click', () => {
            if (!/^\d{6}$/.test(code.value)) {
                status.textContent = 'Ingresa el código de 6 dígitos que recibiste por correo.';
                return;
            }
            return perform(form.dataset.verifyUrl, { email: email.value, code: code.value }, 'Correo verificado. Ya puedes revisar tu reserva.', true);
        });
        const resetVerification = () => {
            const nextEmail = normalizeEmail(email.value);
            if (nextEmail === currentEmail) return;
            currentEmail = nextEmail;
            revision++;
            pending = false;
            form.dataset.emailVerified = 'false';
            updateBookingSubmit(form);
            controls.hidden = false;
            codeControls.hidden = true;
            issue.disabled = false;
            verify.disabled = true;
            code.disabled = true;
            code.value = '';
            status.textContent = 'Si cambias el correo, verifica la nueva dirección antes de continuar.';
            ensureCooldownTimer();
            refreshCooldown();
        };
        email.addEventListener('input', resetVerification);
        email.addEventListener('change', resetVerification);
        refreshCooldown();
    });
}
