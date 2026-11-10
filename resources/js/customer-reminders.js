const root = document.querySelector('[data-customer-reminders]');

if (root) {
    const enableButton = root.querySelector('[data-reminder-enable]');
    const disableButton = root.querySelector('[data-reminder-disable]');
    const frequency = root.querySelector('[data-reminder-frequency]');
    const status = root.querySelector('[data-reminder-status]');
    const publicKey = root.dataset.vapidPublicKey;
    const ios = /iphone|ipad|ipod/i.test(navigator.userAgent)
        || (/macintosh/i.test(navigator.userAgent) && navigator.maxTouchPoints > 1);
    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    const supported = window.isSecureContext && 'serviceWorker' in navigator
        && 'PushManager' in window && 'Notification' in window;

    function setStatus(message) {
        status.textContent = message;
    }

    function decodeKey(value) {
        const padding = '='.repeat((4 - value.length % 4) % 4);
        const raw = atob((value + padding).replace(/-/g, '+').replace(/_/g, '/'));
        return Uint8Array.from(raw, (character) => character.charCodeAt(0));
    }

    async function saveSettings(enabled) {
        const response = await fetch(root.dataset.settingsUrl, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ enabled, frequency_months: Number(frequency.value) }),
        });
        if (!response.ok) {
            throw new Error(response.status === 422
                ? 'Falta configurar las notificaciones o registrar este dispositivo.'
                : 'No se pudo guardar la preferencia. Inténtalo de nuevo.');
        }
        return response.json();
    }

    enableButton?.addEventListener('click', async () => {
        if (!supported || (ios && !standalone)) {
            setStatus(ios && !standalone
                ? 'En iPhone o iPad, instala BarberHub en la pantalla de inicio para activar notificaciones.'
                : 'Este navegador no admite notificaciones push o no usa una conexión HTTPS.');
            return;
        }
        if (!publicKey) {
            setStatus('Las notificaciones todavía no están configuradas para esta barbería.');
            return;
        }
        enableButton.disabled = true;
        try {
            const permission = Notification.permission === 'granted'
                ? 'granted'
                : await Notification.requestPermission();
            if (permission !== 'granted') {
                setStatus(permission === 'denied'
                    ? 'El permiso está bloqueado. Cámbialo en los ajustes del navegador para activar los avisos.'
                    : 'No se activaron las notificaciones. Puedes intentarlo de nuevo.');
                return;
            }
            await navigator.serviceWorker.register('/service-worker.js', { scope: '/' });
            const registration = await navigator.serviceWorker.ready;
            let subscription = await registration.pushManager.getSubscription();
            if (!subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: decodeKey(publicKey),
                });
            }
            const response = await fetch(root.dataset.subscribeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify(subscription.toJSON()),
            });
            if (!response.ok) {
                throw new Error('No se pudo registrar este dispositivo. Inténtalo de nuevo.');
            }
            const result = await saveSettings(true);
            root.dataset.enabled = 'true';
            disableButton.disabled = false;
            setStatus(result.due_at
                ? `Recordatorio activado. Próximo aviso: ${new Intl.DateTimeFormat('es', { dateStyle: 'long', timeZone: root.dataset.timezone }).format(new Date(result.due_at))}.`
                : 'Recordatorio activado.');
        } catch (error) {
            setStatus(error instanceof Error ? error.message : 'No se pudieron activar los recordatorios.');
        } finally {
            enableButton.disabled = false;
        }
    });

    disableButton?.addEventListener('click', async () => {
        disableButton.disabled = true;
        try {
            await saveSettings(false);
            root.dataset.enabled = 'false';
            setStatus('Recordatorios desactivados para esta barbería.');
        } catch (error) {
            setStatus(error instanceof Error ? error.message : 'No se pudo desactivar el recordatorio.');
            disableButton.disabled = false;
        }
    });

    if (!supported) {
        setStatus('Este navegador no admite notificaciones push o no usa una conexión HTTPS.');
    } else if (ios && !standalone) {
        setStatus('En iPhone o iPad, instala BarberHub en la pantalla de inicio para activar notificaciones.');
    } else if (!publicKey) {
        setStatus('Las notificaciones todavía no están configuradas para esta barbería.');
    }
}
