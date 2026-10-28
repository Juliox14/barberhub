

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

if (document.querySelector('[data-audit-poller]')) {
    import('./security-audit-poller.js').then(({ createSecurityAuditPoller }) => {
        document.querySelectorAll('[data-audit-poller]').forEach((root) => {
            createSecurityAuditPoller({ root, window, document }).start();
        });
    });
}
