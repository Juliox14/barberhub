@php
    $invitationErrors = $errors->getBag('customerInvitation'.$customer->id);
    $invitationUrl = (int) session('invitation_customer_id') === $customer->id ? session('invitation_url') : null;
@endphp

<x-modal name="invite-customer-{{ $customer->id }}" :show="$invitationErrors->isNotEmpty() || $invitationUrl !== null" max-width="lg" backdrop-class="bg-black/65" focusable>
    <div class="space-y-5 bg-[#111820] p-5 text-left sm:p-6" role="dialog" aria-modal="true" aria-labelledby="invitation-title-{{ $customer->id }}" x-data="{ copied: false, copyFailed: false, sending: false }">
        <header>
            <h2 id="invitation-title-{{ $customer->id }}" class="text-lg font-semibold text-white">Invitar a {{ $customer->name }}</h2>
            <p class="mt-2 text-sm leading-6 text-slate-400">El cliente recibirá un enlace para crear su cuenta o iniciar sesión y confirmar la vinculación de su historial. Es personal, de un solo uso y vence en 48 horas.</p>
        </header>

        @if ($invitationUrl)
            <div class="space-y-3 rounded-lg border border-amber-300/40 bg-amber-300/10 p-4">
                <p class="text-sm text-amber-100" role="status">{{ session('status') }}</p>
                <label for="invitation-url-{{ $customer->id }}" class="block text-xs font-semibold text-slate-200">Enlace de invitación</label>
                <input id="invitation-url-{{ $customer->id }}" x-ref="invitationUrl" type="text" readonly value="{{ $invitationUrl }}" class="block min-h-11 w-full rounded-lg border border-slate-600 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300" x-on:click="$el.select()">
                <button type="button" x-on:click="try { await navigator.clipboard.writeText($refs.invitationUrl.value); copied = true; copyFailed = false; } catch { copied = false; copyFailed = true; $refs.invitationUrl.focus(); $refs.invitationUrl.select(); }" class="min-h-11 rounded-lg border border-slate-600 px-3 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Copiar enlace</button>
                <p x-cloak x-show="copied" class="text-sm text-emerald-200" role="status">Enlace copiado.</p>
                <p x-cloak x-show="copyFailed" class="text-sm text-amber-100" role="status">No se pudo copiar automáticamente. El enlace está seleccionado para copiarlo manualmente.</p>
            </div>
        @else
            <form method="POST" action="{{ route('tenant.customer-invitations.store', [$barbershop, $customer, ...request()->only(['customers_page', 'sort', 'direction'])]) }}" class="space-y-4" x-on:submit="sending = true">
                @csrf
                <div>
                    <label for="invitation-email-{{ $customer->id }}" class="mb-2 block text-sm font-semibold text-slate-200">Correo electrónico del cliente</label>
                    <input id="invitation-email-{{ $customer->id }}" name="email" type="email" value="{{ $invitationErrors->isNotEmpty() ? old('email') : $customer->email }}" required maxlength="255" autocomplete="email" @if ($invitationErrors->has('email')) aria-invalid="true" aria-describedby="invitation-error-{{ $customer->id }}" @endif class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                    @if ($invitationErrors->has('email'))
                        <p id="invitation-error-{{ $customer->id }}" class="mt-2 text-sm text-rose-300" role="alert">{{ $invitationErrors->first('email') }}</p>
                    @endif
                </div>
                <button type="submit" x-bind:disabled="sending" class="min-h-11 w-full rounded-lg bg-amber-300 px-4 py-2 text-sm font-semibold text-[#17130b] hover:bg-amber-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 disabled:cursor-wait disabled:opacity-60" x-text="sending ? 'Enviando…' : 'Enviar invitación'">Enviar invitación</button>
            </form>
        @endif

        <div class="flex justify-end">
            <button type="button" x-on:click="$dispatch('close')" class="min-h-11 rounded-lg border border-slate-600 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">Cerrar</button>
        </div>
    </div>
</x-modal>
