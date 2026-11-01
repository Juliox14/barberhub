const callbacks = {
    success: 'barberHubRecaptchaSuccess',
    expired: 'barberHubRecaptchaExpired',
    error: 'barberHubRecaptchaError',
};

export function initializeRecaptchaModal(document, window) {
    document.querySelectorAll('[data-recaptcha-modal]').forEach((wrapper) => {
        const dialog = wrapper.querySelector('[data-recaptcha-dialog]');
        const trigger = wrapper.closest('form')?.querySelector('[data-recaptcha-open]');
        const closeButton = wrapper.querySelector('[data-recaptcha-close]');
        const status = wrapper.querySelector('[data-recaptcha-status]');
        const form = wrapper.closest('form');

        if (!dialog || !trigger || !closeButton || !status || !form) {
            return;
        }

        let isOpen = false;

        const setOpen = (open) => {
            isOpen = open;
            wrapper.hidden = !open;
            wrapper.style.display = open ? 'flex' : 'none';
            trigger.hidden = open;
            trigger.style.display = open ? 'none' : 'block';
            trigger.setAttribute('aria-expanded', String(open));

            if (open) {
                dialog.focus();
            } else {
                trigger.focus();
            }
        };

        const report = (message) => {
            status.textContent = message;
        };

        trigger.addEventListener('click', () => setOpen(true));
        closeButton.addEventListener('click', () => setOpen(false));
        wrapper.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && isOpen) {
                event.preventDefault();
                setOpen(false);
            }
        });
        const getTokenField = () => form.querySelector('[name="g-recaptcha-response"]');

        form.addEventListener('submit', (event) => {
            if (getTokenField()?.value.trim()) {
                return;
            }

            event.preventDefault();
            report('Completa la verificación antes de continuar.');
            setOpen(true);
        });

        window[callbacks.success] = (token) => {
            const tokenField = getTokenField();

            if (tokenField && typeof token === 'string') {
                tokenField.value = token;
            }

            report('Verificación completada. Continúa para enviar el formulario.');
            setOpen(false);
        };
        window[callbacks.expired] = () => {
            const tokenField = getTokenField();

            if (tokenField) {
                tokenField.value = '';
            }
            report('La verificación venció. Inténtalo de nuevo.');
            setOpen(true);
        };
        window[callbacks.error] = () => {
            const tokenField = getTokenField();

            if (tokenField) {
                tokenField.value = '';
            }
            report('No se pudo completar la verificación. Inténtalo de nuevo.');
            setOpen(true);
        };

        setOpen(true);
    });
}
