export function initializeBookingWizard(document) {
    const media = document.defaultView?.matchMedia?.('(max-width: 1023px)');
    if (!media) return;

    document.querySelectorAll('[data-booking-wizard]').forEach((root) => {
        const back = root.querySelector('[data-wizard-back]');
        const next = root.querySelector('[data-wizard-next]');
        const status = root.querySelector('[data-wizard-status]');
        const booking = root.querySelector('[data-public-booking]');
        if (!back || !next || !status || !booking) return;
        let step = Number(root.dataset.initialStep) || 1;
        const labels = ['Seleccionar servicio', 'Seleccionar fecha y horario', 'Datos personales y confirmación'];
        const canAdvance = () => {
            if (!root.querySelector('[name="service_id"]:checked')) return false;
            if (step === 1) return true;
            return booking.dataset.availabilityReady === 'true'
                && !!root.querySelector('[name="slot"]:checked')
                && !!root.querySelector('[name="date"]').value
                && root.querySelector('[name="date"]').validity.valid;
        };
        const render = () => {
            if (!media.matches) {
                delete root.dataset.wizardEnhanced;
                return;
            }
            root.dataset.wizardEnhanced = 'true';
            root.dataset.wizardStep = String(step);
            back.hidden = step === 1;
            next.hidden = step === 3;
            next.disabled = !canAdvance();
            status.textContent = `Paso ${step} de 4 · ${labels[step - 1]}`;
            root.querySelectorAll('[data-progress-step]').forEach((item) => {
                if (Number(item.dataset.progressStep) === step) item.setAttribute('aria-current', 'step');
                else item.removeAttribute('aria-current');
            });
        };
        const go = (destination) => {
            step = destination;
            render();
            root.querySelector(`[data-step-panel="${step}"]`).focus();
        };
        next.addEventListener('click', () => {
            if (media.matches && step < 3 && canAdvance()) go(step + 1);
        });
        back.addEventListener('click', () => {
            if (media.matches && step > 1) go(step - 1);
        });
        root.addEventListener('change', render);
        root.querySelector('[data-date-options]')?.addEventListener('click', render);
        root.addEventListener('invalid', (event) => {
            if (!media.matches) return;
            const destination = { service_id: 1, date: 2, barber_id: 2, slot: 2 }[event.target.name] ?? 3;
            go(destination);
        }, true);
        media.addEventListener('change', render);
        render();
    });
}
