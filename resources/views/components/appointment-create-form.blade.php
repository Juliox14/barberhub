@props(['barbershop', 'date', 'canManageAppointments', 'barbers', 'services', 'customers'])

<form id="appointment-create" method="POST" action="{{ route('tenant.appointments.store', $barbershop) }}" class="space-y-5">
    @csrf
    <input type="hidden" name="form_context" value="create-appointment">
    @if ($canManageAppointments)
        <div>
            <label for="barber_id" class="mb-2 block text-sm font-semibold text-slate-200">Barbero</label>
            <select id="barber_id" name="barber_id" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                <option value="">Seleccionar barbero</option>
                @foreach ($barbers as $barberOption)
                    <option value="{{ $barberOption->id }}" @selected(old('form_context') === 'create-appointment' && (string) old('barber_id') === (string) $barberOption->id)>{{ $barberOption->display_name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div>
        <label for="service_id" class="mb-2 block text-sm font-semibold text-slate-200">Servicio</label>
        <select id="service_id" name="service_id" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
            <option value="">Seleccionar servicio</option>
            @foreach ($services as $service)
                <option value="{{ $service->id }}" @selected(old('form_context') === 'create-appointment' && (string) old('service_id') === (string) $service->id)>{{ $service->name }} · {{ $service->duration_minutes }} min</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="customer_id" class="mb-2 block text-sm font-semibold text-slate-200">Cliente @unless ($canManageAppointments)<span class="font-normal text-slate-400">(elige uno)</span>@else<span class="font-normal text-slate-400">(opcional)</span>@endunless</label>
        <select id="customer_id" name="customer_id" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
            <option value="">{{ $canManageAppointments ? 'Sin cliente' : 'Seleccionar cliente existente' }}</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected(old('form_context') === 'create-appointment' && (string) old('customer_id') === (string) $customer->id)>{{ $customer->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="new_customer_name" class="mb-2 block text-sm font-semibold text-slate-200">Otro (cliente sin registrar)</label>
        <input id="new_customer_name" name="new_customer_name" type="text" maxlength="255" value="{{ old('form_context') === 'create-appointment' ? old('new_customer_name') : '' }}" autocomplete="off" class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
        <p class="mt-1 text-xs text-slate-400">Se guardará como cliente de la barbería, sin crear una cuenta.</p>
    </div>
    <div>
        <label for="starts_at" class="mb-2 block text-sm font-semibold text-slate-200">Inicio</label>
        <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('form_context') === 'create-appointment' ? old('starts_at', $date.'T09:00') : $date.'T09:00' }}" required class="block min-h-11 w-full rounded-lg border border-slate-700 bg-[#0b1117] px-3 py-2 text-sm text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
    </div>
</form>
