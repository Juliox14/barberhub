const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
const isIOS = /iphone|ipad|ipod/i.test(window.navigator.userAgent) && !window.MSStream;
const installButton = document.querySelector('[data-pwa-install]');
const iosInstructions = document.querySelector('[data-ios-install]');
const unavailableMessage = document.querySelector('[data-pwa-unavailable]');
const offlineStatus = document.querySelector('[data-pwa-offline]');
let installPrompt;

function updateConnectionStatus() {
    offlineStatus?.classList.toggle('hidden', window.navigator.onLine);
}

window.addEventListener('online', updateConnectionStatus);
window.addEventListener('offline', updateConnectionStatus);
updateConnectionStatus();

if (!window.isSecureContext || !('serviceWorker' in navigator)) {
    if (!isStandalone && unavailableMessage) {
        unavailableMessage.classList.remove('hidden');
    }
} else {
    navigator.serviceWorker.register('/service-worker.js', { scope: '/' }).catch(() => {
        // PWA support is optional; the regular application remains usable.
    });
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
