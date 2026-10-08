# Appointment foundation

## Goal
Create the internal appointment domain foundation so tenant/admin appointment management can rely on tenant-safe associations, service-derived duration, schedule window validation, and overlap prevention before any public booking UI exists.

## Tasks
- [x] Preserve current local state before implementation.
  - Evidence: `git status --short --branch` showed `main` ahead of `origin/main` by 6 with pre-existing `M package-lock.json` and `?? docs/next-session-handoff.md`; neither file was edited. Created feature branch `appointment-foundation` before implementation.
- [x] Explore appointment-adjacent model, migration, factory, validation, and test conventions.
  - Evidence: read project handoff, Engram context/search, `BARBERHUB_CONTEXT.md`, existing business-hour/barber-availability task files, relevant models, migrations, factories, controllers, feature tests, installed package versions, route list, Laravel docs, and applicable Laravel/testing/work-unit rules.
- [x] Add appointment schema/model/factory and relationships.
  - Evidence: added `appointments` migration, `Appointment` model/factory, and relationships on `Barbershop`, `Barber`, `Service`, and `Customer`.
- [x] Add domain validation for tenant integrity, slot granularity, schedule windows, and barber overlaps.
  - Evidence: added `AppointmentScheduler` for internal appointment creation with same-tenant active barber/service/customer checks, 15-minute start slots, service-derived end time, business-hour bounds, barber-availability bounds, and scheduled-overlap prevention.
- [x] Add focused tests for appointment tenant integrity, time-window validation, slot granularity, duration derivation, and active overlap prevention.
  - Evidence: `php artisan test --compact tests/Feature/AppointmentFoundationTest.php` RED before implementation failed with missing `App\Support\AppointmentScheduler` and `App\Models\Appointment`; final run passed 7 tests / 16 assertions.
- [x] Run formatting and verification.
  - Evidence:
    - `vendor/bin/pint --dirty --format agent`: passed.
    - `php artisan test --compact tests/Feature/AppointmentFoundationTest.php`: passed 7 tests / 16 assertions.
    - `php artisan test --compact tests/Feature/AppointmentFoundationTest.php tests/Feature/TenantBarberAvailabilityManagementTest.php tests/Feature/TenantBusinessHourManagementTest.php tests/Feature/TenantServiceManagementTest.php`: passed 38 tests / 154 assertions.
    - `php artisan test --compact tests/Feature/TenantBarberProfileManagementTest.php tests/Feature/TenantMembershipManagementTest.php tests/Feature/TenantDashboardFlowTest.php`: passed 29 tests / 110 assertions.
    - `git diff --check`: passed.
- [x] Close the work unit according to repository policy.
  - Evidence: committed as `9e5c485 2026-10-08 ADD appointment foundation schema` and `5f6d052 2026-10-08 ADD appointment scheduling rules`; evidence file committed separately after those behavior commits. `package-lock.json` and `docs/next-session-handoff.md` remain local/uncommitted.

## Scope decisions
- Start with internal tenant/admin appointment foundation only; no public booking yet.
- Slot granularity is 15 minutes.
- Appointment duration is derived from `services.duration_minutes`; callers provide the start time, not the end time.
- Appointment belongs to one `Barbershop` and associates a `Barber`, `Service`, and nullable `Customer` when applicable.
- Barber, service, and customer must belong to the same tenant/barbershop as the appointment.
- The service and barber must be active to create an active appointment.
- Appointment start/end must fit inside the open `business_hours` record for the weekday.
- Appointment start/end must fit inside the barber's active `barber_availabilities` record for the weekday.
- Prevent overlapping active appointments for the same barber.
- MVP statuses: `scheduled`, `completed`, `cancelled`; only `scheduled` blocks barber overlap.
- Store `starts_at` and derived `ends_at` as datetimes; schedule windows use the local date's weekday and existing time-only weekly rules.
- Defer payments, notifications, buffers, breaks, date exceptions, holidays, public booking, advanced slot search, and UI polish.
- Keep `package-lock.json` intentionally local/uncommitted.
- Keep `docs/next-session-handoff.md` local/uncommitted unless the user explicitly asks to commit it.

## Evidence
- Branch: `appointment-foundation`.
- Commits: `9e5c485 2026-10-08 ADD appointment foundation schema`; `5f6d052 2026-10-08 ADD appointment scheduling rules`.
- RED: `php artisan test --compact tests/Feature/AppointmentFoundationTest.php` failed before implementation: missing `App\Support\AppointmentScheduler` and `App\Models\Appointment`.
- GREEN: `php artisan test --compact tests/Feature/AppointmentFoundationTest.php` passed: 7 tests / 16 assertions.
- Related tests: appointment/business-hour/barber-availability/service tests passed 38 tests / 154 assertions; barber-profile/membership/dashboard tests passed 29 tests / 110 assertions.
- Formatting: `vendor/bin/pint --dirty --format agent` passed.
- Whitespace: `git diff --check` passed.
