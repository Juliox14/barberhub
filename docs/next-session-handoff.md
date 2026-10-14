# BarberHub Frontend Continuation Handoff

Last updated: 2026-10-10

## Repository state

- Intended integration branch: `main`.
- Remote: `origin` (`https://github.com/Juliox14/barberhub.git`).
- The appointment foundation is complete and must be available before beginning UI work.
- `package-lock.json` was refreshed by the Windows-side `npm install`; preserve it with the handoff commit.
- The Windows laptop is the intended local development environment. Run `php artisan serve` and `npm run dev` there; do not run duplicate WSL servers.

## Product and architecture sources of truth

Read these before editing:

1. `BARBERHUB_CONTEXT.md` — product boundaries, multi-tenant security rules, roles, and future modules.
2. `routes/web.php` — current browser entry points and route naming.
3. `app/Http/Middleware/EnsureTenantMembership.php` — tenant access boundary.
4. `app/Support/AppointmentScheduler.php` — appointment domain constraints.
5. `tests/Feature/AppointmentFoundationTest.php` — executable behavior contract for appointments.

## Current backend capabilities

The project uses a shared PostgreSQL database with logical tenant isolation. Operational records belong to a `Barbershop` tenant through `barbershop_id`; do not trust a tenant ID from the browser without authorization.

### Platform scope

Platform administrators manage barbershops through:

- `/platform/dashboard`
- `/platform/barbershops`

Controllers:

- `app/Http/Controllers/PlatformDashboardController.php`
- `app/Http/Controllers/PlatformBarbershopController.php`

### Tenant scope

Authenticated tenant routes are protected by `tenant.member` and live under:

```text
/barbershops/{barbershop:slug}/...
```

Implemented areas:

- Tenant dashboard
- Membership management
- Barber profiles
- Service catalog
- Business hours
- Barber availability

Relevant views currently exist under `resources/views/tenant/`; they are functional but have not received a cohesive visual design pass.

### Appointment foundation

Backend-only appointment scheduling is ready:

- `appointments` migration, model, factory, and model relationships.
- `AppointmentScheduler` creates appointments transactionally.
- A barber, service, and optional customer must belong to the target barber shop.
- Barber and service must be active.
- Appointments begin on 15-minute boundaries.
- End time comes from `Service::duration_minutes`.
- The appointment must fit the business hours and barber availability for that weekday.
- A scheduled appointment cannot overlap another scheduled appointment for the same barber.

There is intentionally no appointment controller, route, or Blade screen yet.

## Recommended frontend work unit

Start with **internal tenant appointment management**, not public booking.

### First visual slice

Create a tenant-scoped appointment management screen that allows an owner or admin to:

1. Open an appointments page for the selected barber shop.
2. See a date-oriented list of scheduled appointments.
3. Create an appointment by selecting an active barber, active service, optional customer, and start time.
4. Receive validation feedback from the existing scheduling rules.
5. Keep every query and selected record scoped to the route barber shop.

### Explicitly defer

Do not add these without a separate product decision:

- Public customer booking
- Payments
- Notifications
- Buffers or breaks
- Holiday/date exceptions
- Advanced availability search
- Calendar integrations
- Facial analysis or recommendations

## UX direction to decide before coding

The next agent should ask the product owner for visual references or an explicit direction before reshaping the current Blade/Tailwind interface. Confirm:

- visual style and color palette,
- desktop/mobile priority,
- sidebar versus top navigation,
- calendar versus list-first appointment presentation,
- information density and primary call to action.

Do not invent a generic dashboard design without that input.

## Technical conventions

- Laravel + Blade + Tailwind CSS + Vite.
- User-visible UI copy is Spanish; code identifiers remain English.
- Preserve tenant authorization in middleware/controllers; never enforce it only in Blade.
- Reuse the existing layouts/components before creating parallel UI patterns:
  - `resources/views/components/tenant-layout.blade.php`
  - `resources/views/components/platform-layout.blade.php`
  - `resources/views/layouts/app.blade.php`
- For behavior changes, add/update meaningful PHPUnit feature coverage.
- Before modifying PHP, read relevant Laravel and testing skills/rules.
- Run focused tests and `vendor/bin/pint --dirty --format agent` after PHP changes.
- Do not add dependencies without approval.

## First commands on the laptop

```bash
git pull origin main
composer install
npm install
php artisan migrate
php artisan test --compact tests/Feature/AppointmentFoundationTest.php
npm run dev
php artisan serve
```

If the development database already contains the migrations, `php artisan migrate` will safely report that there is nothing pending.

## Useful verification commands

```bash
php artisan route:list --except-vendor
php artisan test --compact tests/Feature/AppointmentFoundationTest.php
php artisan test --compact tests/Feature/TenantBarberAvailabilityManagementTest.php tests/Feature/TenantBusinessHourManagementTest.php tests/Feature/TenantServiceManagementTest.php
vendor/bin/pint --dirty --format agent
git status --short --branch
```
