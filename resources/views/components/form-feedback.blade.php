@php
    $validationMessages = collect($errors->getBags())
        ->flatMap(fn ($bag) => $bag->all())
        ->unique()
        ->values();

    $status = session('status');
    $successMessage = match ($status) {
        'profile-updated' => 'Tu perfil se actualizó correctamente.',
        'password-updated' => 'Tu contraseña se actualizó correctamente.',
        'verification-link-sent' => __('A new verification link has been sent to your email address.'),
        default => $status,
    };
@endphp

@if ($validationMessages->isNotEmpty() || $successMessage)
    <div {{ $attributes->class(['bh-notifications']) }}>
        @if ($validationMessages->isNotEmpty())
            <x-ui.alert type="error" title="Revisá los datos del formulario">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($validationMessages as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        @if ($successMessage)
            <x-ui.alert title="Operación exitosa">{{ $successMessage }}</x-ui.alert>
        @endif
    </div>
@endif
