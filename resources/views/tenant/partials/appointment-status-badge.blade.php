@php
    $statusPresentation = match ($status) {
        \App\Models\Appointment::STATUS_SCHEDULED => ['label' => 'Programada', 'classes' => 'border-amber-300/40 bg-amber-300/10 text-amber-100'],
        \App\Models\Appointment::STATUS_COMPLETED => ['label' => 'Completada', 'classes' => 'border-emerald-400/30 bg-emerald-400/10 text-emerald-200'],
        \App\Models\Appointment::STATUS_CANCELLED => ['label' => 'Cancelada', 'classes' => 'border-rose-400/30 bg-rose-400/10 text-rose-200'],
        \App\Models\Appointment::STATUS_NO_SHOW => ['label' => 'No asistió', 'classes' => 'border-slate-500 bg-slate-800 text-slate-200'],
        default => ['label' => $status, 'classes' => 'border-slate-600 bg-slate-800 text-slate-200'],
    };
@endphp
<span class="inline-flex min-h-7 items-center rounded-md border px-2.5 py-1 text-xs font-semibold {{ $statusPresentation['classes'] }}">{{ $statusPresentation['label'] }}</span>
