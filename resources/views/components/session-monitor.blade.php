<div
    data-session-monitor
    data-account-id="{{ auth()->id() }}"
    data-status-url="{{ route('session.status', absolute: false) }}"
    data-login-revoked-url="{{ route('login', ['session' => 'revoked'], absolute: false) }}"
    data-login-expired-url="{{ route('login', ['session' => 'expired'], absolute: false) }}"
></div>
