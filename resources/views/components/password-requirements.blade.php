@props(['tone' => 'dark'])

<div class="mt-2 text-sm {{ $tone === 'light' ? 'text-gray-600' : 'text-slate-300' }}" data-password-requirements data-tone="{{ $tone }}">
    <p>La contraseña debe tener al menos 12 caracteres e incluir mayúsculas, minúsculas, números y símbolos.</p>
    <ul class="mt-2 hidden space-y-1" aria-label="Requisitos de la contraseña">
        <li data-password-requirement="length"><span>Al menos 12 caracteres</span>: <span class="{{ $tone === 'light' ? 'text-gray-500' : 'text-slate-400' }}" data-requirement-state>Pendiente</span></li>
        <li data-password-requirement="uppercase"><span>Una mayúscula</span>: <span class="{{ $tone === 'light' ? 'text-gray-500' : 'text-slate-400' }}" data-requirement-state>Pendiente</span></li>
        <li data-password-requirement="lowercase"><span>Una minúscula</span>: <span class="{{ $tone === 'light' ? 'text-gray-500' : 'text-slate-400' }}" data-requirement-state>Pendiente</span></li>
        <li data-password-requirement="number"><span>Un número</span>: <span class="{{ $tone === 'light' ? 'text-gray-500' : 'text-slate-400' }}" data-requirement-state>Pendiente</span></li>
        <li data-password-requirement="symbol"><span>Un símbolo</span>: <span class="{{ $tone === 'light' ? 'text-gray-500' : 'text-slate-400' }}" data-requirement-state>Pendiente</span></li>
    </ul>
</div>
