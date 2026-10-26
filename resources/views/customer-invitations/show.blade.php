<x-guest-layout>
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
</x-guest-layout>
