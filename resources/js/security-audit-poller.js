const POLL_INTERVAL = 15_000;
const REQUEST_TIMEOUT = 10_000;

export function createSecurityAuditPoller({
    root,
    document,
    window,
    fetcher = window.fetch.bind(window),
    setIntervalFn = window.setInterval.bind(window),
    clearIntervalFn = window.clearInterval.bind(window),
    setTimeoutFn = window.setTimeout.bind(window),
    clearTimeoutFn = window.clearTimeout.bind(window),
}) {
    let intervalId = null;
    let requestController = null;
    let requestSequence = 0;
    let stopped = false;
    let suspended = false;
    let listenersAttached = false;

    const status = root.querySelector('[data-audit-status]');

    function setStatus(message) {
        if (status && status.textContent !== message) {
            status.textContent = message;
        }
    }

    function abortRequest() {
        requestSequence += 1;
        requestController?.abort();
        requestController = null;
    }

    function clearPollingInterval() {
        if (intervalId !== null) {
            clearIntervalFn(intervalId);
            intervalId = null;
        }
    }

    function stop() {
        if (stopped) {
            return;
        }

        stopped = true;
        suspended = false;
        abortRequest();
        clearPollingInterval();
        if (listenersAttached) {
            document.removeEventListener('visibilitychange', onVisibilityChange);
            window.removeEventListener('pagehide', onPageHide);
            window.removeEventListener('pageshow', onPageShow);
            listenersAttached = false;
        }
    }

    function suspend() {
        if (stopped || suspended) {
            return;
        }

        suspended = true;
        abortRequest();
        clearPollingInterval();
    }

    function resume() {
        if (stopped || !suspended) {
            return;
        }

        suspended = false;
        start();
    }

    async function refresh() {
        if (stopped || document.visibilityState !== 'visible' || requestController) {
            return;
        }

        const controller = new AbortController();
        const sequence = ++requestSequence;
        requestController = controller;
        let timedOut = false;
        const timeoutId = setTimeoutFn(() => {
            timedOut = true;
            controller.abort();
        }, REQUEST_TIMEOUT);

        try {
            const response = await fetcher(root.dataset.endpoint, {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                redirect: 'follow',
                signal: controller.signal,
            });

            if (sequence !== requestSequence || stopped) {
                return;
            }
            if (response.redirected || [401, 403, 419].includes(response.status)) {
                stop();
                setStatus('La sesión o el permiso de administrador ya no están disponibles. Recarga la página para volver a acceder.');
                return;
            }
            if (!response.ok) {
                throw new Error('refresh-failed');
            }

            const payload = await response.json();
            if (sequence !== requestSequence || stopped || typeof payload.html !== 'string') {
                return;
            }

            const results = root.querySelector('[data-audit-results]');
            if (!results || results.innerHTML === payload.html) {
                setStatus('Actualizado automáticamente cada 15 segundos.');
                return;
            }
            if (document.activeElement && results.contains(document.activeElement)) {
                setStatus('Hay cambios disponibles. Se mostrarán cuando termines de usar la tabla.');
                return;
            }

            results.innerHTML = payload.html;
            setStatus('Actualizado automáticamente cada 15 segundos.');
        } catch (error) {
            if (sequence === requestSequence && !stopped && (error?.name !== 'AbortError' || timedOut)) {
                setStatus('No se pudo actualizar la bitácora. Se conserva la información visible; se volverá a intentar.');
            }
        } finally {
            clearTimeoutFn(timeoutId);
            if (sequence === requestSequence) {
                requestController = null;
            }
        }
    }

    function onVisibilityChange() {
        if (stopped || suspended) {
            return;
        }
        if (document.visibilityState === 'hidden') {
            abortRequest();
            setStatus('Actualización pausada mientras la pestaña está oculta.');
        } else if (!stopped) {
            setStatus('Actualización reanudada.');
            refresh();
        }
    }

    function onPageHide(event) {
        if (event.persisted) {
            suspend();
            return;
        }

        stop();
    }

    function onPageShow(event) {
        if (event.persisted) {
            resume();
        }
    }

    function start() {
        if (stopped || suspended || intervalId !== null) {
            return;
        }

        if (!listenersAttached) {
            document.addEventListener('visibilitychange', onVisibilityChange);
            window.addEventListener('pagehide', onPageHide);
            window.addEventListener('pageshow', onPageShow);
            listenersAttached = true;
        }
        if (document.visibilityState === 'visible') {
            refresh();
        }
        intervalId = setIntervalFn(refresh, POLL_INTERVAL);
    }

    return {
        start,
        refresh,
        stop,
    };
}
