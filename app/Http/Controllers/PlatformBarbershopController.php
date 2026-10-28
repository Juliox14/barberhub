<?php

namespace App\Http\Controllers;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use App\Support\LatinAmericanTimezones;
use App\Support\SecurityAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class PlatformBarbershopController extends Controller
{
    public function index(): View
    {
        $barbershops = Barbershop::query()
            ->withCount('memberships')
            ->latest()
            ->paginate(10);

        return view('platform.barbershops-index', [
            'barbershops' => $barbershops,
        ]);
    }

    public function create(): View
    {
        return view('platform.barbershops-create', [
            'timezones' => LatinAmericanTimezones::groupedOptions(),
        ]);
    }

    public function store(Request $request, SecurityAuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(Barbershop::class, 'slug'),
            ],
            'timezone' => ['required', 'string', Rule::in(LatinAmericanTimezones::identifiers())],
            'status' => ['nullable', Rule::in([Barbershop::STATUS_ACTIVE, Barbershop::STATUS_INACTIVE])],
            'owner_email' => ['required', 'email', Rule::exists(User::class, 'email')],
        ], [
            'slug.regex' => 'El slug solo puede usar minúsculas, números y guiones entre palabras.',
            'owner_email.exists' => 'El correo de la persona propietaria debe pertenecer a una cuenta existente.',
            'timezone.in' => 'La zona horaria debe pertenecer a Latinoamérica.',
        ]);

        $barbershop = DB::transaction(function () use ($validated): Barbershop {
            $barbershop = Barbershop::query()->create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'timezone' => $validated['timezone'],
                'status' => $validated['status'] ?? Barbershop::STATUS_ACTIVE,
            ]);

            $owner = User::query()
                ->where('email', $validated['owner_email'])
                ->firstOrFail();

            Membership::query()->firstOrCreate(
                [
                    'user_id' => $owner->id,
                    'barbershop_id' => $barbershop->id,
                ],
                [
                    'role' => Membership::ROLE_OWNER,
                    'status' => Membership::STATUS_ACTIVE,
                ],
            );

            return $barbershop;
        });
        $auditLogger->record('platform.barbershop.created', $request->user(), $barbershop->id, $barbershop->id, [
            'name' => $barbershop->name,
            'slug' => $barbershop->slug,
            'status' => $barbershop->status,
            'timezone' => $barbershop->timezone,
        ], $request);

        return redirect()
            ->route('platform.barbershops.index')
            ->with('status', 'Barbería creada con su membresía propietaria inicial.');
    }

    public function edit(Barbershop $barbershop): View
    {
        return view('platform.barbershops-edit', [
            'barbershop' => $barbershop,
            'timezones' => LatinAmericanTimezones::groupedOptions(),
        ]);
    }

    public function update(Request $request, Barbershop $barbershop, SecurityAuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(Barbershop::class, 'slug')->ignore($barbershop),
            ],
            'timezone' => ['required', 'string', Rule::in(LatinAmericanTimezones::identifiers())],
            'status' => ['required', Rule::in([Barbershop::STATUS_ACTIVE, Barbershop::STATUS_INACTIVE])],
        ], [
            'slug.regex' => 'El slug solo puede usar minúsculas, números y guiones entre palabras.',
            'timezone.in' => 'La zona horaria debe pertenecer a Latinoamérica.',
        ]);

        if ($barbershop->update($validated)) {
            $auditLogger->record('platform.barbershop.updated', $request->user(), $barbershop->id, $barbershop->id, [
                'name' => $barbershop->name,
                'slug' => $barbershop->slug,
                'status' => $barbershop->status,
                'timezone' => $barbershop->timezone,
            ], $request);
        }

        return redirect()
            ->route('platform.barbershops.index')
            ->with('status', 'Barbería actualizada correctamente.');
    }

    public function destroy(Request $request, Barbershop $barbershop, SecurityAuditLogger $auditLogger): RedirectResponse
    {
        $subjectId = $barbershop->id;
        $metadata = [
            'name' => $barbershop->name,
            'slug' => $barbershop->slug,
            'status' => $barbershop->status,
            'timezone' => $barbershop->timezone,
        ];
        if ($barbershop->delete()) {
            $auditLogger->record('platform.barbershop.deleted', $request->user(), $subjectId, $subjectId, $metadata, $request);
        }

        return redirect()
            ->route('platform.barbershops.index')
            ->with('status', 'Barbería eliminada correctamente.');
    }
}
