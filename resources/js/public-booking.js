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

export function initializePublicBooking(document, fetchImplementation = fetch) {
    document.querySelectorAll('[data-public-booking]').forEach((form) => {
        const issue = form.querySelector('[data-issue-code]');
        const verify = form.querySelector('[data-verify-code]');
        const status = form.querySelector('[data-code-status]');
        const email = form.querySelector('[name="email"]');
        const code = form.querySelector('[data-email-code]');
        if (!issue || !verify || !status || !email || !code) return;
        const csrf = form.querySelector('[name="_token"]').value;
        const perform = async (button, url, payload, successMessage) => {
            if (!email.reportValidity()) return;
            button.disabled = true;
            status.textContent = 'Procesando…';
            try {
                const result = await requestVerification(url, payload, csrf, fetchImplementation);
                status.textContent = result.status ?? successMessage;
            } catch (error) {
                status.textContent = error.message || 'No pudimos conectarnos. Intenta nuevamente.';
            } finally {
                button.disabled = false;
            }
        };
        issue.addEventListener('click', () => perform(issue, form.dataset.issueUrl, { email: email.value }, 'Revisa tu correo.'));
        verify.addEventListener('click', () => {
            if (!/^\d{6}$/.test(code.value)) {
                status.textContent = 'Ingresa el código de 6 dígitos que recibiste por correo.';
                return;
            }
            perform(verify, form.dataset.verifyUrl, { email: email.value, code: code.value }, 'Correo verificado. Ya puedes revisar tu reserva.');
        });
        email.addEventListener('input', () => {
            code.value = '';
            status.textContent = 'Si cambias el correo, verifica la nueva dirección antes de continuar.';
        });
    });
}
