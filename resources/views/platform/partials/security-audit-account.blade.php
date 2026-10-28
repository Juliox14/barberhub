@if ($account)
    <p class="font-medium text-slate-200">{{ $account->name }}</p>
    <p class="mt-1 break-all text-xs text-slate-400">{{ $account->email }}</p>
    <p class="mt-1 text-xs text-slate-500">Cuenta #{{ $account->id }}</p>
    @if ($account->memberships->isNotEmpty())
        <p class="mt-2 text-xs font-medium text-slate-300">Miembro activo en</p>
        <ul class="mt-1 space-y-1 text-xs text-slate-400">
            @foreach ($account->memberships as $membership)
                @if ($membership->barbershop)
                    <li>{{ $membership->barbershop->name }}</li>
                @endif
            @endforeach
        </ul>
    @endif
    @if ($account->customers->isNotEmpty())
        <p class="mt-2 text-xs font-medium text-slate-300">Cliente en</p>
        <ul class="mt-1 space-y-1 text-xs text-slate-400">
            @foreach ($account->customers as $customer)
                @if ($customer->barbershop)
                    <li>{{ $customer->barbershop->name }}</li>
                @endif
            @endforeach
        </ul>
    @endif
@else
    <p>{{ $fallback }}</p>
@endif
