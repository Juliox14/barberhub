<x-guest-layout>
    @if ($existingAccount)
        <header class="mb-8">
            <h1 class="text-3xl font-bold tracking-[-0.035em] text-white">Confirmar vinculación</h1>
            <p class="mt-2 text-sm leading-6 text-slate-300">La barbería {{ $invitation->barbershop->name }} quiere vincular la ficha de cliente de {{ $invitation->customer->name }} con tu cuenta.</p>
        </header>

        <div class="space-y-4 rounded-xl border border-slate-700 bg-[#0b1015] p-5">
            <p class="text-sm leading-6 text-slate-300">Al confirmar, tu cuenta tendrá acceso a esta ficha y se conservarán las citas que ya están asociadas. Esta acción no combinará fichas de otras barberías.</p>
            <p class="text-sm text-slate-400">Invitación dirigida a <span class="font-semibold text-slate-200">{{ $invitation->email }}</span>. Vence el {{ $invitation->expires_at->format('d/m/Y H:i') }}.</p>
        </div>

        <form method="POST" action="{{ route('customer-invitations.accept', $token) }}" class="mt-6">
            @csrf
            <button type="submit" class="flex min-h-12 w-full items-center justify-center rounded-xl bg-amber-300 px-4 py-3.5 text-sm font-bold text-[#1a1409] outline-none transition hover:bg-amber-200 focus-visible:ring-2 focus-visible:ring-amber-100 focus-visible:ring-offset-2 focus-visible:ring-offset-[#10151a]">
                Confirmar vinculación
            </button>
        </form>
    @else
        <header class="mb-8">
            <h1 class="text-3xl font-bold tracking-[-0.035em] text-white">Completa tu cuenta</h1>
            <p class="mt-2 text-sm leading-6 text-slate-300">{{ $invitation->barbershop->name }} te invita a guardar tus datos y vincularlos con tu historial de cliente.</p>
        </header>

        <div class="mb-6 rounded-xl border border-slate-700 bg-[#0b1015] p-5">
            <p class="text-sm leading-6 text-slate-300">Tus citas existentes se conservarán. Revisa o actualiza tu nombre y teléfono antes de crear la cuenta.</p>
            <p class="mt-3 text-sm text-slate-300">Correo de la invitación <span class="ml-1 font-semibold text-white">{{ $invitation->email }}</span></p>
            <p class="mt-1 text-xs text-slate-400">Este correo no se puede cambiar desde el enlace. La invitación vence el {{ $invitation->expires_at->format('d/m/Y H:i') }}.</p>
        </div>

        <form method="POST" action="{{ route('customer-invitations.register', $token) }}" class="space-y-5">
            @csrf

            <div>
                <label for="name" class="block text-sm font-semibold text-slate-100">Nombre completo</label>
                <input id="name" class="mt-2 block w-full rounded-xl border border-slate-600 bg-[#0b1015] px-4 py-3 text-sm text-white placeholder:text-slate-500 shadow-inner shadow-black/20 outline-none transition focus:border-amber-300 focus:ring-2 focus:ring-amber-300/25" type="text" name="name" value="{{ old('name', $invitation->customer->name) }}" required autocomplete="name" />
                @error('name')
                    <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="phone" class="block text-sm font-semibold text-slate-100">Teléfono</label>
                <input id="phone" class="mt-2 block w-full rounded-xl border border-slate-600 bg-[#0b1015] px-4 py-3 text-sm text-white placeholder:text-slate-500 shadow-inner shadow-black/20 outline-none transition focus:border-amber-300 focus:ring-2 focus:ring-amber-300/25" type="tel" name="phone" value="{{ old('phone', $invitation->customer->phone) }}" autocomplete="tel" />
                @error('phone')
                    <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold text-slate-100">Contraseña</label>
                <input id="password" class="mt-2 block w-full rounded-xl border border-slate-600 bg-[#0b1015] px-4 py-3 text-sm text-white placeholder:text-slate-500 shadow-inner shadow-black/20 outline-none transition focus:border-amber-300 focus:ring-2 focus:ring-amber-300/25" type="password" name="password" required autocomplete="new-password" />
                @error('password')
                    <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-semibold text-slate-100">Confirmar contraseña</label>
                <input id="password_confirmation" class="mt-2 block w-full rounded-xl border border-slate-600 bg-[#0b1015] px-4 py-3 text-sm text-white placeholder:text-slate-500 shadow-inner shadow-black/20 outline-none transition focus:border-amber-300 focus:ring-2 focus:ring-amber-300/25" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>

            <button type="submit" class="flex min-h-12 w-full items-center justify-center rounded-xl bg-amber-300 px-4 py-3.5 text-sm font-bold text-[#1a1409] outline-none transition hover:bg-amber-200 focus-visible:ring-2 focus-visible:ring-amber-100 focus-visible:ring-offset-2 focus-visible:ring-offset-[#10151a]">
                Crear cuenta y vincular
            </button>
        </form>
    @endif
</x-guest-layout>
