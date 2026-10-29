<?php

namespace App\Http\Controllers;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\OwnerAccountInvitation;
use App\Support\LatinAmericanTimezones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
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
            'timezone' => ['required', 'string', Rule::in(LatinAmericanTimezones::identifiers())],
            'status' => ['nullable', Rule::in([Barbershop::STATUS_ACTIVE, Barbershop::STATUS_INACTIVE])],
            'owner_type' => ['nullable', Rule::in(['existing', 'new'])],
            'owner_name' => [Rule::requiredIf($request->input('owner_type') === 'new'), 'nullable', 'string', 'max:255'],
            'owner_email' => [
                'required',
                'email',
                $request->input('owner_type') === 'new'
                    ? Rule::unique(User::class, 'email')
                    : Rule::exists(User::class, 'email'),
            ],
        ], [
            'slug.regex' => 'El slug solo puede usar minúsculas, números y guiones entre palabras.',
            'owner_email.exists' => 'El correo de la persona propietaria debe pertenecer a una cuenta existente.',
            'owner_email.unique' => 'Ya existe una cuenta con este correo. Selecciona la cuenta existente para asignarla.',
            'owner_name.required' => 'Indica el nombre de la persona propietaria.',
            'timezone.in' => 'La zona horaria debe pertenecer a Latinoamérica.',
        ]);

        $owner = DB::transaction(function () use ($validated): User {
            $barbershop = Barbershop::query()->create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'timezone' => $validated['timezone'],
                'status' => $validated['status'] ?? Barbershop::STATUS_ACTIVE,
            ]);

            if (($validated['owner_type'] ?? 'existing') === 'new') {
                $owner = User::query()->create([
                    'name' => $validated['owner_name'],
                    'email' => $validated['owner_email'],
                    'password' => Hash::make(Str::random(64)),
                    'is_platform_admin' => false,
                ]);
            } else {
                $owner = User::query()
                    ->where('email', $validated['owner_email'])
                    ->firstOrFail();
            }

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

            return $owner;
        });

        if (($validated['owner_type'] ?? 'existing') === 'new') {
            $token = Password::broker()->createToken($owner);
            $owner->notify(new OwnerAccountInvitation($token, $validated['name']));
        }

        return redirect()
            ->route('platform.barbershops.index')
            ->with('status', ($validated['owner_type'] ?? 'existing') === 'new'
                ? 'Barbería creada. Se envió una invitación para que la persona propietaria configure su cuenta.'
                : 'Barbería creada con su membresía propietaria inicial.');
    }

    public function edit(Barbershop $barbershop): View
    {
        return view('platform.barbershops-edit', [
            'barbershop' => $barbershop,
            'timezones' => LatinAmericanTimezones::groupedOptions(),
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
            'timezone' => ['required', 'string', Rule::in(LatinAmericanTimezones::identifiers())],
            'status' => ['required', Rule::in([Barbershop::STATUS_ACTIVE, Barbershop::STATUS_INACTIVE])],
        ], [
            'slug.regex' => 'El slug solo puede usar minúsculas, números y guiones entre palabras.',
            'timezone.in' => 'La zona horaria debe pertenecer a Latinoamérica.',
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
