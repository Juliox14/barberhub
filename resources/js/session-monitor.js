const SESSION_NOTICES = {
    revoked: 'Tu sesión se cerró porque iniciaste sesión en otro dispositivo.',
    expired: 'Tu sesión expiró. Inicia sesión nuevamente.',
};
const POLL_INTERVAL = 30_000;
const REQUEST_TIMEOUT = 8_000;
const LEASE_DURATION = 40_000;

export function createSessionMonitor({ window: browser, document, fetch: request, accountId, endpoint, revokedLoginUrl, expiredLoginUrl, passwordRenewalUrl, twoFactorChallengeUrl, twoFactorSettingsUrl }) {
    const accountKey = encodeURIComponent(String(accountId));
    const channelName = `barberhub-session-${accountKey}`;
    const leaseKey = `${channelName}-lease`;
    const ownerId = `${Date.now()}-${Math.random()}`;
    const sessionFetch = typeof request === 'function' ? request : browser.fetch?.bind(browser);
    let channel = null;
    let active = false;
    let leader = false;
    let inFlight = false;
    let pollTimer = null;
    let retryTimer = null;
    let lockAbort = null;
    let lockRequestPending = false;
    let requestAbort = null;
    let requestCancel = null;
    let requestTimeout = null;
    let leaseTimer = null;
    let fallbackStorage = null;

    function isVisible() {
        return document.visibilityState === 'visible';
    }

    function post(message) {
        try {
            channel?.postMessage(message);
        } catch {
            // Cross-tab messaging is an enhancement; server ownership remains authoritative.
        }
    }

    function redirectToLogin(status) {
        const notice = SESSION_NOTICES[status];
        const target = status === 'revoked' ? revokedLoginUrl : expiredLoginUrl;
        if (!notice || !target) return;
        try {
            browser.sessionStorage.setItem('barberhub-session-notice', notice);
        } catch {
            // The login URL also carries a fixed server-rendered notice state.
        }
        browser.location.assign(target);
    }

    function handleSessionEnd(status) {
        if (!active) return;
        post({ type: 'session-ended', status });
        stop({ notify: false });
        redirectToLogin(status);
    }

    function redirectForCredentialPolicy(status, notify = true) {
        const target = {
            password_expired: passwordRenewalUrl,
            two_factor_challenge: twoFactorChallengeUrl,
            two_factor_required: twoFactorSettingsUrl,
        }[status];
        if (!active || typeof target !== 'string' || !target) return;
        if (notify) post({ type: 'credentials-required', status });
        stop({ notify: false });
        browser.location.assign(target);
    }

    function stopPolling({ notify = true } = {}) {
        if (pollTimer !== null) {
            browser.clearInterval(pollTimer);
            pollTimer = null;
        }
        if (requestAbort !== null) requestAbort.abort();
        if (requestCancel !== null) requestCancel();
        if (requestTimeout !== null) {
            browser.clearTimeout(requestTimeout);
            requestTimeout = null;
        }
        leader = false;
        releaseFallbackLease();
        if (notify) post({ type: 'available' });
    }

    function releaseFallbackLease() {
        if (leaseTimer !== null) {
            browser.clearInterval(leaseTimer);
            leaseTimer = null;
        }
        if (fallbackStorage !== null) {
            try {
                const lease = readLease(fallbackStorage);
                if (lease?.owner === ownerId) fallbackStorage.removeItem(leaseKey);
            } catch {
                // Blocked or malformed storage disables the fallback election safely.
            }
            fallbackStorage = null;
        }
    }

    async function checkSession() {
        if (!active || !leader || !isVisible() || inFlight || !sessionFetch) return;
        if (!ownsFallbackLease()) return;

        inFlight = true;
        const controller = new AbortController();
        requestAbort = controller;
        let finishTimeout;
        let finishCancellation;
        const timeout = new Promise((resolve) => {
            finishTimeout = resolve;
        });
        const cancellation = new Promise((resolve) => {
            finishCancellation = resolve;
        });
        requestCancel = () => finishCancellation(null);
        const timeoutId = browser.setTimeout(() => {
            controller.abort();
            finishTimeout(null);
        }, REQUEST_TIMEOUT);
        requestTimeout = timeoutId;

        try {
            const requestResult = (async () => {
                const response = await sessionFetch(endpoint, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                    signal: controller.signal,
                });

                return response.status === 401 || response.status === 403
                    ? { status: response.status, body: await response.json() }
                    : { status: response.status };
            })();
            const result = await Promise.race([requestResult, timeout, cancellation]);

            if (!active || !leader || !isVisible()) return;
            if (result?.status === 401
                && (result.body?.status === 'revoked' || result.body?.status === 'expired')) {
                handleSessionEnd(result.body.status);
            } else if (result?.status === 401 || result?.status === 403) {
                redirectForCredentialPolicy(result.body?.status);
            }
        } catch {
            // Offline, timeout, malformed JSON, and server errors never imply revocation.
        } finally {
            browser.clearTimeout(timeoutId);
            if (requestTimeout === timeoutId) requestTimeout = null;
            if (requestAbort === controller) requestAbort = null;
            if (requestCancel === finishCancellation) requestCancel = null;
            inFlight = false;
        }
    }

    function startPolling() {
        if (!active || !isVisible() || leader) return;
        leader = true;
        if (fallbackStorage !== null) {
            renewFallbackLease();
            leaseTimer = browser.setInterval(renewFallbackLease, 10_000);
        }
        checkSession();
        pollTimer = browser.setInterval(checkSession, POLL_INTERVAL);
    }

    function retryElection(delay = POLL_INTERVAL) {
        if (!active || !isVisible() || retryTimer !== null) return;
        retryTimer = browser.setTimeout(() => {
            retryTimer = null;
            electLeader();
        }, delay);
    }

    function acquireWithWebLock() {
        const locks = browser.navigator?.locks;
        if (!locks || typeof locks.request !== 'function') return false;
        if (lockRequestPending) return true;

        lockRequestPending = true;
        const electionAbort = new AbortController();
        lockAbort = electionAbort;
        try {
            locks.request(channelName, { ifAvailable: true }, async (lock) => {
                if (!active || !isVisible() || electionAbort.signal.aborted || !lock) {
                    retryElection();
                    return;
                }
                startPolling();
                await new Promise((resolve) => {
                    if (electionAbort.signal.aborted) {
                        resolve();
                        return;
                    }
                    electionAbort.signal.addEventListener('abort', resolve, { once: true });
                });
            }).catch(() => {
                if (active && isVisible()) acquireFallbackLease();
            }).finally(() => {
                lockRequestPending = false;
                if (lockAbort === electionAbort) lockAbort = null;
                if (active && isVisible() && !leader && !retryTimer) retryElection(200);
            });
            return true;
        } catch {
            lockRequestPending = false;
            lockAbort = null;
            return false;
        }
    }

    function readLease(storage) {
        try {
            return JSON.parse(storage.getItem(leaseKey) || 'null');
        } catch {
            return null;
        }
    }

    function relinquishFallbackLease() {
        stopPolling();
    }

    function ownsFallbackLease() {
        if (fallbackStorage === null) return true;
        const current = readLease(fallbackStorage);
        if (current && current.owner !== ownerId && current.expires > Date.now()) {
            relinquishFallbackLease();
            return false;
        }
        try {
            fallbackStorage.setItem(leaseKey, JSON.stringify({ owner: ownerId, expires: Date.now() + LEASE_DURATION }));
            const confirmed = readLease(fallbackStorage);
            if (confirmed?.owner !== ownerId) {
                relinquishFallbackLease();
                return false;
            }
            return true;
        } catch {
            // Storage can be blocked; continue in bounded per-tab mode rather than crash.
            releaseFallbackLease();
            return true;
        }
    }

    function renewFallbackLease() {
        if (!fallbackStorage || !leader) return;
        ownsFallbackLease();
    }

    function acquireFallbackLease() {
        try {
            fallbackStorage = browser.localStorage;
            const current = readLease(fallbackStorage);
            if (current && current.owner !== ownerId && current.expires > Date.now()) {
                fallbackStorage = null;
                retryElection();
                return;
            }
            if (!ownsFallbackLease()) {
                retryElection();
                return;
            }
            startPolling();
        } catch {
            fallbackStorage = null;
            // Without storage or Web Locks, per-tab polling is the bounded safe fallback.
            startPolling();
        }
    }

    function electLeader() {
        if (!active || !isVisible() || leader) return;
        if (!acquireWithWebLock()) acquireFallbackLease();
    }

    function onMessage(event) {
        if (event.data?.type === 'session-ended') {
            if (!active) return;
            stop({ notify: false });
            redirectToLogin(event.data.status);
        } else if (event.data?.type === 'credentials-required') {
            redirectForCredentialPolicy(event.data.status, false);
        } else if (event.data?.type === 'available' && isVisible()) {
            if (retryTimer !== null) {
                browser.clearTimeout(retryTimer);
                retryTimer = null;
            }
            retryTimer = browser.setTimeout(() => {
                retryTimer = null;
                electLeader();
            }, 200);
        }
    }

    function onStorage(event) {
        if (event.key !== leaseKey) return;
        const changedLease = (() => {
            try {
                return JSON.parse(event.newValue || 'null');
            } catch {
                return null;
            }
        })();
        if (leader && changedLease?.owner !== ownerId && changedLease?.expires > Date.now()) {
            relinquishFallbackLease();
        } else if (event.newValue === null) {
            electLeader();
        }
    }

    function onVisibilityChange() {
        if (!isVisible()) {
            if (retryTimer !== null) {
                browser.clearTimeout(retryTimer);
                retryTimer = null;
            }
            lockAbort?.abort();
            stopPolling();
            return;
        }
        if (leader) checkSession();
        else electLeader();
    }

    function onFocus() {
        if (!isVisible()) return;
        if (leader) checkSession();
        else electLeader();
    }

    function onPageHide() {
        if (!active) return;
        if (retryTimer !== null) browser.clearTimeout(retryTimer);
        retryTimer = null;
        lockAbort?.abort();
        stopPolling();
    }

    function onPageShow() {
        if (active && isVisible()) electLeader();
    }

    function start() {
        if (active) return;
        active = true;
        try {
            if (typeof browser.BroadcastChannel === 'function') {
                channel = new browser.BroadcastChannel(channelName);
                channel.addEventListener?.('message', onMessage);
                if (!channel.addEventListener) channel.onmessage = onMessage;
            }
        } catch {
            channel = null;
        }
        browser.addEventListener('storage', onStorage);
        browser.addEventListener('focus', onFocus);
        browser.addEventListener('pagehide', onPageHide);
        browser.addEventListener('pageshow', onPageShow);
        document.addEventListener('visibilitychange', onVisibilityChange);
        if (isVisible()) electLeader();
    }

    function stop({ notify = true } = {}) {
        if (!active) return;
        active = false;
        lockAbort?.abort();
        stopPolling({ notify });
        if (retryTimer !== null) browser.clearTimeout(retryTimer);
        retryTimer = null;
        document.removeEventListener('visibilitychange', onVisibilityChange);
        browser.removeEventListener('storage', onStorage);
        browser.removeEventListener('focus', onFocus);
        browser.removeEventListener('pagehide', onPageHide);
        browser.removeEventListener('pageshow', onPageShow);
        channel?.close();
        channel = null;
    }

    return { start, stop };
}
