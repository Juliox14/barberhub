

import Alpine from 'alpinejs';
import { createSessionMonitor } from './session-monitor.js';

window.Alpine = Alpine;

Alpine.start();

document.querySelectorAll('[data-session-monitor]').forEach((element) => {
    createSessionMonitor({
        window,
        document,
        accountId: element.dataset.accountId,
        endpoint: element.dataset.statusUrl,
        revokedLoginUrl: element.dataset.loginRevokedUrl,
        expiredLoginUrl: element.dataset.loginExpiredUrl,
    }).start();
});
