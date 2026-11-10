<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCustomerPreferencesRequest;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Support\TenantBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerProfileController extends Controller
{
    public function show(Request $request, Barbershop $barbershop): Response
    {
        $customer = $this->customerFor($request, $barbershop);
        $completedAppointments = $customer->appointments()
            ->where('barbershop_id', $barbershop->id)
            ->where('status', Appointment::STATUS_COMPLETED);
        $visitCount = (clone $completedAppointments)->count();
        $appointments = $completedAppointments
            ->with(['service', 'barber'])
            ->latest('starts_at')
            ->paginate(10)
            ->withQueryString();
        $preferences = $customer->preference?->preferences ?? [];
        $barbers = $barbershop->barbers()->where('active', true)->orderBy('display_name')->get();
        $services = $barbershop->services()->where('active', true)->orderBy('name')->get();
        $activeServiceIds = $services->modelKeys();
        $activeBarberIds = $barbers->modelKeys();

        return response()->view('public.customer-profile', [
            'barbershop' => $barbershop,
            'branding' => TenantBranding::resolve($barbershop),
            'customer' => $customer,
            'appointments' => $appointments,
            'visitCount' => $visitCount,
            'preferences' => $preferences,
            'barbers' => $barbers,
            'services' => $services,
            'activeServiceIds' => $activeServiceIds,
            'activeBarberIds' => $activeBarberIds,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function updatePreferences(UpdateCustomerPreferencesRequest $request, Barbershop $barbershop): RedirectResponse
    {
        $customer = $this->customerFor($request, $barbershop);
        $preference = $customer->preference()->firstOrNew();
        $existingPreferences = $preference->preferences ?? [];
        $validated = $request->validated();
        $updatedPreferences = array_merge($existingPreferences, [
            'preferred_barber_id' => isset($validated['preferred_barber_id']) ? (int) $validated['preferred_barber_id'] : null,
            'preferred_service_id' => isset($validated['preferred_service_id']) ? (int) $validated['preferred_service_id'] : null,
            'notes' => $validated['notes'] ?? '',
        ]);

        $preference->preferences = $updatedPreferences;
        $preference->save();

        return redirect()->route('customer.profile.show', $barbershop)->with('status', 'Tus preferencias se guardaron.');
    }

    private function customerFor(Request $request, Barbershop $barbershop): Customer
    {
        return Customer::query()
            ->where('barbershop_id', $barbershop->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }
}
