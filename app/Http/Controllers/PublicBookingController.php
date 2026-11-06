<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Notifications\PublicBookingConfirmed;
use App\Support\AppointmentHistory;
use App\Support\AppointmentPushDispatcher;
use App\Support\AppointmentScheduler;
use App\Support\GuestBookingVerification;
use App\Support\GuestCustomerResolver;
use App\Support\ModuleContainment;
use App\Support\PublicBookingAvailability;
use App\Support\PublicBookingManagement;
use App\Support\TenantBranding;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PublicBookingController extends Controller
{
    public function create(Request $request, Barbershop $barbershop, PublicBookingAvailability $availability): Response
    {
        $this->ensureAvailable($barbershop);
        $validated = $request->validate([
            'service_id' => ['nullable', 'integer'],
            'barber_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $today = CarbonImmutable::today($barbershop->timezone);
        $date = $validated['date'] ?? $today->format('Y-m-d');
        $this->ensureDate($barbershop, $date);
        $services = $barbershop->services()->where('active', true)->orderBy('name')->get();
        $barbers = $barbershop->barbers()->where('active', true)->orderBy('display_name')->get();
        $service = isset($validated['service_id']) ? $services->firstWhere('id', $validated['service_id']) : null;
        abort_if(isset($validated['service_id']) && $service === null, 404);
        $barberId = $validated['barber_id'] ?? null;
        abort_if($barberId !== null && ! $barbers->contains('id', $barberId), 404);

        return response()->view('public.booking', [
            'barbershop' => $barbershop, 'branding' => TenantBranding::resolve($barbershop),
            'services' => $services, 'barbers' => $barbers, 'service' => $service, 'barberId' => $barberId,
            'date' => $date, 'today' => $today->format('Y-m-d'), 'lastDate' => $today->addDays(90)->format('Y-m-d'),
            'slots' => $service ? $availability->slots($barbershop, $service, $date, $barberId) : [],
            'verifiedAccount' => $request->user()?->email_verified_at !== null,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function summary(Request $request, Barbershop $barbershop, PublicBookingAvailability $availability, GuestBookingVerification $verification): Response|JsonResponse
    {
        $this->ensureAvailable($barbershop);
        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'slot' => ['required', 'string', 'regex:/\A\d+\|\d{4}-\d{2}-\d{2}T\d{2}:\d{2}\z/'],
            'name' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/u'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);
        $account = $request->user()?->email_verified_at !== null ? $request->user() : null;
        $email = mb_strtolower(trim($account?->email ?? $validated['email']));
        abort_unless($account !== null || $verification->verified($request, $barbershop, $email), 403, 'Verifica tu correo antes de continuar.');
        [$barberId, $startsAt] = explode('|', $validated['slot'], 2);
        $date = substr($startsAt, 0, 10);
        $this->ensureDate($barbershop, $date);
        $service = $barbershop->services()->where('active', true)->findOrFail($validated['service_id']);
        $slot = collect($availability->slots($barbershop, $service, $date, (int) $barberId))->firstWhere('starts_at', $startsAt);
        if ($slot === null) {
            throw ValidationException::withMessages(['slot' => 'Ese horario ya no está disponible. Selecciona otro.']);
        }

        $draftId = Str::random(64);
        $summary = [
            'service' => $service->name, 'barber' => $slot['barber_name'], 'starts_at' => $startsAt,
            'timezone' => $barbershop->timezone, 'price' => $service->price, 'duration' => $service->duration_minutes,
            'name' => trim($validated['name']), 'email' => $email, 'phone' => $validated['phone'] ?? null,
        ];
        Cache::put($this->draftKey($request, $barbershop, $draftId), [
            'user_id' => $account?->id, 'service_id' => $service->id, 'barber_id' => $slot['barber_id'],
            'summary' => $summary,
        ], 600);

        return $this->respond($request, $barbershop, 'public.booking-summary', ['draft_id' => $draftId, 'summary' => $summary]);
    }

    public function store(Request $request, Barbershop $barbershop, AppointmentScheduler $scheduler, GuestBookingVerification $verification, AppointmentHistory $history, GuestCustomerResolver $guestCustomers, AppointmentPushDispatcher $pushDispatcher): Response|JsonResponse|RedirectResponse
    {
        $this->ensureAvailable($barbershop);
        $validated = $request->validate(['draft_id' => ['required', 'string', 'size:64', 'alpha_num:ascii']]);
        $key = $this->draftKey($request, $barbershop, $validated['draft_id']);
        $response = Cache::lock($key.':lock', 30)->get(function () use ($key, $request, $barbershop, $scheduler, $verification, $history, $guestCustomers, $pushDispatcher): Response|JsonResponse|RedirectResponse {
            $draft = Cache::get($key);
            abort_unless(is_array($draft), 404, 'El resumen venció. Selecciona nuevamente tu horario.');
            $account = $request->user()?->email_verified_at !== null ? $request->user() : null;
            abort_unless(($draft['user_id'] ?? null) === $account?->id, 403);
            if (isset($draft['receipt'])) {
                return $this->respond($request, $barbershop, 'public.booking-confirmed', $draft['receipt']);
            }
            $summary = $draft['summary'];
            abort_unless($account !== null || $verification->verified($request, $barbershop, $summary['email']), 403, 'La verificación venció. Verifica tu correo nuevamente.');
            if ($account !== null && mb_strtolower(trim($account->email)) !== $summary['email']) {
                abort(403);
            }
            $startsAt = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $summary['starts_at'], $barbershop->timezone);
            if (! $startsAt->isFuture()) {
                throw ValidationException::withMessages(['slot' => 'Ese horario ya pasó. Selecciona otro.']);
            }
            try {
                $appointment = DB::transaction(function () use ($barbershop, $draft, $summary, $account, $startsAt, $scheduler, $history, $guestCustomers): Appointment {
                    $barber = $barbershop->barbers()->where('active', true)->findOrFail($draft['barber_id']);
                    $service = $barbershop->services()->where('active', true)->findOrFail($draft['service_id']);
                    if ((string) $service->price !== (string) $summary['price'] || $service->duration_minutes !== $summary['duration']) {
                        throw ValidationException::withMessages(['slot' => 'El servicio cambió. Revisa un nuevo resumen antes de confirmar.']);
                    }
                    $customer = $account !== null
                        ? $barbershop->customers()->where('user_id', $account->id)->orderBy('id')->first()
                        : $guestCustomers->resolve($barbershop, [
                            'name' => $summary['name'], 'email' => $summary['email'], 'phone' => $summary['phone'],
                        ]);
                    $customer ??= Customer::create([
                        'barbershop_id' => $barbershop->id, 'user_id' => $account?->id,
                        'name' => $summary['name'], 'email' => $summary['email'], 'phone' => $summary['phone'],
                    ]);

                    $appointment = $scheduler->schedule($barbershop, $barber, $service, $customer, $startsAt);
                    $history->record(
                        $appointment,
                        'created',
                        $account === null ? 'guest' : 'customer',
                        $account,
                        null,
                        $appointment->getRawOriginal('starts_at'),
                        null,
                        Appointment::STATUS_SCHEDULED,
                    );

                    return $appointment;
                });
            } catch (ValidationException $exception) {
                if ($request->expectsJson()) {
                    throw $exception;
                }

                return redirect()->route('public.booking.create', [
                    'barbershop' => $barbershop, 'service_id' => $draft['service_id'], 'date' => substr($summary['starts_at'], 0, 10),
                ])->withErrors(['slot' => array_merge(...array_values($exception->errors()))])->withInput([
                    'name' => $summary['name'], 'email' => $summary['email'], 'phone' => $summary['phone'],
                ]);
            }
            $receipt = ['confirmed' => true, 'appointment_id' => $appointment->id, 'summary' => $summary, 'email_sent' => false];
            $draft['receipt'] = $receipt;
            Cache::put($key, $draft, 600);
            $pushDispatcher->dispatch($appointment, $account, 'assigned');
            $verification->consume($request, $barbershop, $summary['email']);
            try {
                Notification::route('mail', $summary['email'])->notify(new PublicBookingConfirmed(
                    $barbershop->name,
                    $summary,
                    PublicBookingManagement::url($barbershop, $appointment),
                ));
                $receipt['email_sent'] = true;
            } catch (Throwable $exception) {
                report($exception);
            }
            $draft['receipt'] = $receipt;
            Cache::put($key, $draft, 600);

            return $this->respond($request, $barbershop, 'public.booking-confirmed', $receipt);
        });
        abort_if($response === false, 409, 'La confirmación está en curso. Espera unos segundos.');

        return $response;
    }

    private function ensureAvailable(Barbershop $barbershop): void
    {
        abort_unless($barbershop->status === Barbershop::STATUS_ACTIVE, 404);
        abort_if(app(ModuleContainment::class)->isSuspended($barbershop, 'appointments'), 404);
    }

    private function ensureDate(Barbershop $barbershop, string $date): void
    {
        $validator = validator(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']]);
        $validator->validate();
        $today = CarbonImmutable::today($barbershop->timezone);
        if ($date < $today->format('Y-m-d') || $date > $today->addDays(90)->format('Y-m-d')) {
            throw ValidationException::withMessages(['date' => 'Selecciona una fecha entre hoy y los próximos 90 días.']);
        }
    }

    private function draftKey(Request $request, Barbershop $barbershop, string $draftId): string
    {
        return 'public-booking:draft:'.hash_hmac('sha256', $barbershop->id.'|'.$request->session()->getId().'|'.$draftId, (string) config('app.key'));
    }

    /** @param array<string, mixed> $data */
    private function respond(Request $request, Barbershop $barbershop, string $view, array $data): Response|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json($data)->header('Cache-Control', 'no-store, private');
        }

        return response()->view($view, $data + ['barbershop' => $barbershop, 'branding' => TenantBranding::resolve($barbershop)])->header('Cache-Control', 'no-store, private');
    }
}
