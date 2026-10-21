const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
const isIOS = /iphone|ipad|ipod/i.test(window.navigator.userAgent) && !window.MSStream;
const installButton = document.querySelector('[data-pwa-install]');
const iosInstructions = document.querySelector('[data-ios-install]');
const unavailableMessage = document.querySelector('[data-pwa-unavailable]');
const offlineStatus = document.querySelector('[data-pwa-offline]');
const pushControls = document.querySelector('[data-push-controls]');
const pushButton = document.querySelector('[data-push-enable]');
const pushStatus = document.querySelector('[data-push-status]');
const iosPushInstructions = document.querySelector('[data-ios-push-install]');
let installPrompt;
let serviceWorkerRegistration;

function updateConnectionStatus() {
    offlineStatus?.classList.toggle('hidden', window.navigator.onLine);
}

function setPushStatus(message) {
    if (pushStatus) {
        pushStatus.textContent = message;
    }
}

function decodeApplicationServerKey(value) {
    const padding = '='.repeat((4 - value.length % 4) % 4);
    const encoded = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(encoded);

    return Uint8Array.from(raw, (character) => character.charCodeAt(0));
}

window.addEventListener('online', updateConnectionStatus);
window.addEventListener('offline', updateConnectionStatus);
updateConnectionStatus();

if (!window.isSecureContext || !('serviceWorker' in navigator)) {
    if (!isStandalone && unavailableMessage) {
        unavailableMessage.classList.remove('hidden');
    }
} else {
    serviceWorkerRegistration = navigator.serviceWorker.register('/service-worker.js', { scope: '/' });
    serviceWorkerRegistration.catch(() => {
        // PWA support is optional; the regular application remains usable.
    });
}

if (pushControls && pushButton) {
    const publicKey = pushControls.dataset.vapidPublicKey;
    const supportsPush = window.isSecureContext
        && 'serviceWorker' in navigator
        && 'PushManager' in window
        && 'Notification' in window;

    if (isIOS && !isStandalone) {
        iosPushInstructions?.classList.remove('hidden');
        setPushStatus('Instala BarberHub en la pantalla de inicio para activar notificaciones en iPhone o iPad.');
    } else if (!supportsPush) {
        setPushStatus('Este navegador no admite notificaciones push. Puedes seguir usando BarberHub.');
    } else if (Notification.permission === 'denied') {
        setPushStatus('Las notificaciones están bloqueadas en el navegador. Permítelas en sus ajustes para activarlas.');
    } else if (!publicKey) {
        setPushStatus('Las notificaciones todavía no están configuradas para esta barbería.');
    } else {
        pushButton.classList.remove('hidden');
        setPushStatus(Notification.permission === 'granted'
            ? 'Puedes activar las notificaciones para este dispositivo.'
            : 'Activa las notificaciones para recibir avisos de tu agenda.');

        pushButton.addEventListener('click', async () => {
            pushButton.disabled = true;
            setPushStatus('Preparando las notificaciones…');

            try {
                const permission = Notification.permission === 'granted'
                    ? 'granted'
                    : await Notification.requestPermission();

                if (permission !== 'granted') {
                    setPushStatus(permission === 'denied'
                        ? 'Las notificaciones están bloqueadas. Permítelas en los ajustes del navegador.'
                        : 'No se activaron las notificaciones. Puedes intentarlo de nuevo cuando quieras.');
                    return;
                }

                const registration = await serviceWorkerRegistration;
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: decodeApplicationServerKey(publicKey),
                });
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                const response = await fetch(pushControls.dataset.subscribeUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken ?? '',
                    },
                    body: JSON.stringify(subscription.toJSON()),
                });

                if (!response.ok) {
                    throw new Error(response.status === 403
                        ? 'Solo los barberos activos pueden activar notificaciones.'
                        : 'No se pudo registrar este dispositivo. Inténtalo de nuevo.');
                }

                setPushStatus('Notificaciones activadas para este dispositivo.');
                pushButton.classList.add('hidden');
            } catch (error) {
                setPushStatus(error instanceof Error ? error.message : 'No se pudieron activar las notificaciones.');
            } finally {
                pushButton.disabled = false;
            }
        });
    }
}

if (!isStandalone) {
    if (isIOS) {
        iosInstructions?.classList.remove('hidden');
    } else if (installButton) {
        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            installPrompt = event;
            installButton.classList.remove('hidden');
        });

        installButton.addEventListener('click', async () => {
            if (!installPrompt) {
                return;
            }

            installButton.disabled = true;
            try {
                await installPrompt.prompt();
            } catch {
                // A failed or dismissed prompt must not affect BarberHub.
            } finally {
                installPrompt = null;
                installButton.classList.add('hidden');
                installButton.disabled = false;
            }
        });
    }
}
