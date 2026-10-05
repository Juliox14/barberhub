<?php

namespace App\Http\Controllers;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
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
        return view('platform.barbershops-create');
    }

    public function store(Request $request): RedirectResponse
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
            'timezone' => ['required', 'string', 'max:64'],
            'status' => ['nullable', Rule::in([Barbershop::STATUS_ACTIVE, Barbershop::STATUS_INACTIVE])],
            'owner_email' => ['required', 'email', Rule::exists(User::class, 'email')],
        ], [
            'slug.regex' => 'El slug solo puede usar minúsculas, números y guiones entre palabras.',
            'owner_email.exists' => 'El correo de la persona propietaria debe pertenecer a una cuenta existente.',
        ]);

        DB::transaction(function () use ($validated): void {
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
        });

        return redirect()
            ->route('platform.barbershops.index')
            ->with('status', 'Barbería creada con su membresía propietaria inicial.');
    }

    public function edit(Barbershop $barbershop): View
    {
        return view('platform.barbershops-edit', [
            'barbershop' => $barbershop,
        ]);
    }

    public function update(Request $request, Barbershop $barbershop): RedirectResponse
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
            'timezone' => ['required', 'string', 'max:64'],
            'status' => ['required', Rule::in([Barbershop::STATUS_ACTIVE, Barbershop::STATUS_INACTIVE])],
        ], [
            'slug.regex' => 'El slug solo puede usar minúsculas, números y guiones entre palabras.',
        ]);

        $barbershop->update($validated);

        return redirect()
            ->route('platform.barbershops.index')
            ->with('status', 'Barbería actualizada correctamente.');
    }

    public function destroy(Barbershop $barbershop): RedirectResponse
    {
        $barbershop->delete();

        return redirect()
            ->route('platform.barbershops.index')
            ->with('status', 'Barbería eliminada correctamente.');
    }
}
