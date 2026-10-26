const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
const userAgent = window.navigator.userAgent;
const isIOS = /iphone|ipad|ipod/i.test(userAgent) || (/macintosh/i.test(userAgent) && window.navigator.maxTouchPoints > 1);
const isMobileDevice = /android|iphone|ipad|ipod/i.test(userAgent) || isIOS;
const controls = document.querySelector('[data-pwa-controls]');
const installAlert = document.querySelector('[data-pwa-install-alert]');
const pushAlert = document.querySelector('[data-push-alert]');
const installButton = document.querySelector('[data-pwa-install]');
const installDismiss = document.querySelector('[data-pwa-dismiss="install"]');
const pushDismiss = document.querySelector('[data-pwa-dismiss="push"]');
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

function getStorage(key) {
    try {
        return window.sessionStorage.getItem(key);
    } catch {
        return null;
    }
}

function setStorage(key, value) {
    try {
        window.sessionStorage.setItem(key, value);
    } catch {
        // The alerts remain usable when browser storage is unavailable.
    }
}

function alertStorageKey(type) {
    return `barberhub:pwa:${controls?.dataset.accountId ?? 'guest'}:${type}`;
}

function hideAlert(alert) {
    alert?.classList.add('hidden');
}

function showAlert(alert, type) {
    if (isMobileDevice && !getStorage(alertStorageKey(type))) {
        alert?.classList.remove('hidden');
    }
}

installDismiss?.addEventListener('click', () => {
    setStorage(alertStorageKey('install'), 'dismissed');
    hideAlert(installAlert);
});
pushDismiss?.addEventListener('click', () => {
    setStorage(alertStorageKey('push'), 'dismissed');
    hideAlert(pushAlert);
});

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

if (isMobileDevice && !isStandalone) {
    showAlert(installAlert, 'install');
}

if (isMobileDevice && controls?.dataset.pushSubscribed !== 'true') {
    showAlert(pushAlert, 'push');
}

if (pushControls && pushButton && isMobileDevice && controls?.dataset.pushSubscribed !== 'true') {
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
                hideAlert(pushAlert);
                pushButton.classList.add('hidden');
            } catch (error) {
                setPushStatus(error instanceof Error ? error.message : 'No se pudieron activar las notificaciones.');
            } finally {
                pushButton.disabled = false;
            }
        });
    }
}

if (isMobileDevice && !isStandalone) {
    if (isIOS) {
        iosInstructions?.classList.remove('hidden');
    } else if (installButton) {
        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            installPrompt = event;
            installButton.classList.remove('hidden');
        });

        window.addEventListener('appinstalled', () => {
            hideAlert(installAlert);
            setStorage(alertStorageKey('install'), 'installed');
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
                hideAlert(installAlert);
                installButton.disabled = false;
            }
        });
    }
}
