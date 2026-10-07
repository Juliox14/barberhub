# Tenant barber availability

## Goal
Create tenant-scoped weekly availability for barber profiles so appointments can later calculate slots from business hours plus each barber's recurring schedule.

## Tasks
- [x] Preserve current local state before implementation.
  - Evidence: `git status --short -- ... package-lock.json docs/next-session-handoff.md` showed `M package-lock.json` and `?? docs/next-session-handoff.md`; neither file was edited by this work unit.
- [x] Explore current barber profile, business hour, route, view, and test conventions.
  - Evidence: Read `TenantBusinessHourController`, `TenantBarberController`, related feature tests, models, factories, routes, and tenant Blade views before implementation.
- [x] Add barber availability schema/model/factory and relationships.
  - Evidence: Added `barber_availabilities` migration, `BarberAvailability` model/factory, and `Barber::availabilities()`.
- [x] Implement tenant-scoped barber availability management routes/controller/views.
  - Evidence: Added `/barbershops/{barbershop:slug}/barber-availability` index/store/update routes, tenant authorization, validation, and Spanish Blade UI/nav/dashboard links.
- [x] Add focused feature coverage for authorization, tenant isolation, duplicate weekdays, business-hour bounds, and time validation.
  - Evidence: `php artisan test --compact tests/Feature/TenantBarberAvailabilityManagementTest.php` RED before implementation failed with missing `App\Models\BarberAvailability` and missing route names; final run passed 12 tests / 57 assertions.
- [x] Run formatting and verification.
  - Evidence:
    - `php artisan test --compact tests/Feature/TenantBarberAvailabilityManagementTest.php`: passed 12 tests / 57 assertions.
    - `php artisan test --compact tests/Feature/TenantBusinessHourManagementTest.php`: passed 10 tests / 44 assertions.
    - `php artisan test --compact tests/Feature/TenantBarberProfileManagementTest.php`: passed 9 tests / 38 assertions.
    - `php artisan test --compact tests/Feature/TenantServiceManagementTest.php`: passed 9 tests / 37 assertions.
    - `php artisan test --compact tests/Feature/TenantMembershipManagementTest.php`: passed 9 tests / 41 assertions.
    - `php artisan test --compact tests/Feature/TenantDashboardFlowTest.php`: passed 11 tests / 31 assertions.
    - `vendor/bin/pint --dirty --format agent`: passed.
    - Independent verifier reran focused availability tests (12 tests / 57 assertions), related tenant/business-hours/barber/service/member/dashboard tests (48 tests / 191 assertions), and `git diff --check` successfully.
- [x] Close the work unit according to repository policy.
  - Evidence: pending commit creation; package lock and next-session handoff preserved as local changes.

## Scope decisions
- Availability belongs to a `Barber` profile, and the barber profile belongs to exactly one barbershop.
- MVP stores weekly recurring availability only; date exceptions, breaks, holidays, and multiple shifts per day are out of scope.
- Weekday values reuse `BusinessHour` weekday integers `0-6`.
- Each barber can have at most one availability record per weekday.
- Owner/admin manage availability; regular barbers do not manage it in this MVP.
- Platform admin support access follows existing tenant management convention.
- Availability can only be created for an active barber profile in the current barbershop.
- The selected weekday must have an open business-hour record for the barbershop.
- `starts_at` must be before `ends_at` and the interval must fit inside the barbershop's open business hours for that weekday.
- UI is functional Blade only; polish remains deferred.
- `package-lock.json` remains intentionally local/uncommitted.
- `docs/next-session-handoff.md` remains local and must not be committed.

## Evidence
- Commits: pending.
- RED: `php artisan test --compact tests/Feature/TenantBarberAvailabilityManagementTest.php` failed before implementation with missing `App\\Models\\BarberAvailability` and missing `tenant.barber-availability.*` route names.
- GREEN: `php artisan test --compact tests/Feature/TenantBarberAvailabilityManagementTest.php` passed after implementation: 12 tests, 57 assertions.
- Related tests: `TenantBusinessHourManagementTest` passed 10 tests / 44 assertions; `TenantBarberProfileManagementTest` passed 9 tests / 38 assertions; `TenantServiceManagementTest` passed 9 tests / 37 assertions; `TenantMembershipManagementTest` passed 9 tests / 41 assertions; `TenantDashboardFlowTest` passed 11 tests / 31 assertions.
- Formatting: `vendor/bin/pint --dirty --format agent` passed.
- Independent verification: focused availability tests passed (12 tests, 57 assertions); related tenant/business-hours/barber/service/member/dashboard tests passed (48 tests, 191 assertions); `git diff --check` passed.
- Native review assessment: unavailable/unassessable because the package-local Gentle AI binary is missing (`package-local-binary-missing`); separate verifier was run.
