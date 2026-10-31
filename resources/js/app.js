

import Alpine from 'alpinejs';
import { createSessionMonitor } from './session-monitor.js';
import { createPlatformSidebar } from './platform-sidebar.js';
import { initializePasswordFeedback } from './password-feedback.js';

window.Alpine = Alpine;

Alpine.data('platformSidebar', createPlatformSidebar);
Alpine.start();
initializePasswordFeedback(document);

document.querySelectorAll('[data-session-monitor]').forEach((element) => {
    createSessionMonitor({
        window,
        document,
        accountId: element.dataset.accountId,
        endpoint: element.dataset.statusUrl,
        revokedLoginUrl: element.dataset.loginRevokedUrl,
        expiredLoginUrl: element.dataset.loginExpiredUrl,
        passwordRenewalUrl: element.dataset.passwordRenewalUrl,
        twoFactorChallengeUrl: element.dataset.twoFactorChallengeUrl,
        twoFactorSettingsUrl: element.dataset.twoFactorSettingsUrl,
    }).start();
});

if (document.querySelector('[data-audit-poller]')) {
    import('./security-audit-poller.js').then(({ createSecurityAuditPoller }) => {
        document.querySelectorAll('[data-audit-poller]').forEach((root) => {
            createSecurityAuditPoller({ root, window, document }).start();
        });
    });
}
