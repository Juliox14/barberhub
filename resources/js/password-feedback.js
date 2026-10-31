const requirements = [
    { key: 'length', label: 'Al menos 12 caracteres', matches: (value) => Array.from(value).length >= 12 },
    { key: 'uppercase', label: 'Una mayúscula', matches: (value) => /\p{Lu}/u.test(value) },
    { key: 'lowercase', label: 'Una minúscula', matches: (value) => /\p{Ll}/u.test(value) },
    { key: 'number', label: 'Un número', matches: (value) => /\p{N}/u.test(value) },
    { key: 'symbol', label: 'Un símbolo', matches: (value) => /[\p{Z}\p{S}\p{P}]/u.test(value) },
];

export function passwordRequirements(value) {
    return requirements.map(({ key, label, matches }) => ({ key, label, met: matches(value) }));
}

export function confirmationMessage(password, confirmation) {
    if (confirmation === '') {
        return '';
    }

    return confirmation === password ? 'Las contraseñas coinciden.' : 'Las contraseñas no coinciden.';
}

export function initializePasswordFeedback(document) {
    document.querySelectorAll('[data-password-requirements]').forEach((root) => {
        const form = root.closest('form');
        if (!form || form.dataset.passwordFeedbackInitialized === 'true') {
            return;
        }

        const password = form.querySelector('input[name="password"]');
        const confirmation = form.querySelector('input[name="password_confirmation"]');
        if (!password || !confirmation) {
            return;
        }

        form.dataset.passwordFeedbackInitialized = 'true';
        const isLight = root.dataset.tone === 'light';
        const neutralTextClass = isLight ? 'text-gray-500' : 'text-slate-300';
        const pendingTextClass = isLight ? 'text-gray-500' : 'text-slate-400';
        const fulfilledTextClass = isLight ? 'text-emerald-700' : 'text-emerald-300';
        const mismatchTextClass = isLight ? 'text-rose-700' : 'text-rose-300';
        const status = document.createElement('p');
        status.className = `mt-2 text-sm ${neutralTextClass}`;
        status.setAttribute('data-password-confirmation-status', '');
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        confirmation.insertAdjacentElement('afterend', status);

        const update = () => {
            const results = passwordRequirements(password.value);
            root.querySelectorAll('[data-password-requirement]').forEach((item) => {
                const result = results.find(({ key }) => key === item.dataset.passwordRequirement);
                if (!result) {
                    return;
                }

                const state = item.querySelector('[data-requirement-state]');
                state.textContent = result.met ? 'Cumplido' : 'Pendiente';
                state.className = result.met ? fulfilledTextClass : pendingTextClass;
                item.dataset.met = String(result.met);
            });

            const message = confirmationMessage(password.value, confirmation.value);
            status.textContent = message;
            status.className = `mt-2 text-sm ${message === '' ? neutralTextClass : message === 'Las contraseñas coinciden.' ? fulfilledTextClass : mismatchTextClass}`;
        };

        password.addEventListener('input', update);
        password.addEventListener('change', update);
        confirmation.addEventListener('input', update);
        confirmation.addEventListener('change', update);
        update();
        root.querySelector('ul')?.classList.remove('hidden');
    });
}
