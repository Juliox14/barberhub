<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Miembros de {{ $barbershop->name }}</h2>
    </x-slot>

    <main class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                    <p class="font-semibold">Revisa la información enviada.</p>
                    <ul class="mt-2 list-disc space-y-1 ps-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Agregar o actualizar miembro</h3>
                <p class="mt-1 text-sm text-gray-600">Solo se pueden agregar cuentas existentes por correo electrónico.</p>

                <form method="POST" action="{{ route('tenant.members.store', $barbershop) }}" class="mt-4 grid gap-4 md:grid-cols-4 md:items-end">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Correo</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700">Rol</label>
                        <select id="role" name="role" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($roleLabels as $role => $label)
                                <option value="{{ $role }}" @selected(old('role') === $role)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Estado</label>
                        <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($statusLabels as $status => $label)
                                <option value="{{ $status }}" @selected(old('status', \App\Models\Membership::STATUS_ACTIVE) === $status)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="inline-flex w-full justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2">Guardar miembro</button>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="border-b border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900">Listado de miembros</h3>
                    <p class="mt-1 text-sm text-gray-600">Actualiza el rol o estado de cada persona dentro de esta barbería.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-6 py-3">Nombre</th>
                                <th class="px-6 py-3">Correo</th>
                                <th class="px-6 py-3">Rol</th>
                                <th class="px-6 py-3">Estado</th>
                                <th class="px-6 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($memberships as $membership)
                                <tr>
                                    <td class="px-6 py-4 font-medium text-gray-900">{{ $membership->user->name }}</td>
                                    <td class="px-6 py-4 text-gray-600">{{ $membership->user->email }}</td>
                                    <td class="px-6 py-4">
                                        <form id="membership-update-{{ $membership->id }}" method="POST" action="{{ route('tenant.members.update', [$barbershop, $membership]) }}" class="contents">
                                            @csrf
                                            @method('PUT')
                                            <select name="role" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                @foreach ($roleLabels as $role => $label)
                                                    <option value="{{ $role }}" @selected($membership->role === $role)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    </td>
                                    <td class="px-6 py-4">
                                        <select form="membership-update-{{ $membership->id }}" name="status" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            @foreach ($statusLabels as $status => $label)
                                                <option value="{{ $status }}" @selected($membership->status === $status)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-end gap-2">
                                            <button form="membership-update-{{ $membership->id }}" type="submit" class="rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Actualizar</button>
                                            <form method="POST" action="{{ route('tenant.members.destroy', [$barbershop, $membership]) }}" onsubmit="return confirm('¿Eliminar esta membresía?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-md border border-red-300 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50">Eliminar</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-gray-500">Todavía no hay miembros registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</x-app-layout>
