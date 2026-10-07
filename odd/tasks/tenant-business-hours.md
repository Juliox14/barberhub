# Tenant business hours

## Goal
Create tenant-scoped weekly business hours so each barbershop can define when the shop is open before barber availability and appointment slot calculation are implemented.

## Tasks
- [x] Preserve current local state before implementation.
  - Evidence: `git status --short` showed pre-existing ` M package-lock.json` and `?? docs/next-session-handoff.md`; neither file was edited.
- [x] Explore current tenant, route, model, factory, view, and test conventions.
  - Evidence: read `TenantServiceController`, `TenantBarberController`, `TenantMembershipController`, related feature tests, tenant layout/dashboard/views, factories, and service migration/model before implementation.
- [x] Add business hour schema/model/factory and relationships.
  - Evidence: added `BusinessHour` model/factory, `business_hours` migration with `[barbershop_id, weekday]` uniqueness, and `Barbershop::businessHours()`.
- [x] Implement tenant-scoped business hour management routes/controller/views.
  - Evidence: added tenant routes/controller/index view plus tenant nav/dashboard links; controller authorizes owner/admin/platform-admin and rejects cross-tenant update with 404.
- [x] Add focused feature coverage for authorization, tenant isolation, duplicate weekdays, and time validation.
  - Evidence: `php artisan test --compact tests/Feature/TenantBusinessHourManagementTest.php` first failed RED with missing `App\\Models\\BusinessHour` and route definitions, then passed after implementation: 10 tests, 44 assertions.
- [x] Run formatting and verification.
  - Evidence: `vendor/bin/pint --dirty --format agent` passed; focused and related feature tests passed.
- [x] Close the work unit according to repository policy.
  - Evidence: no commit created; final handoff prepared for parent review.

## Scope decisions
- Business hours belong to exactly one barbershop.
- MVP stores weekly recurring hours only; date exceptions, breaks, holidays, and barber-specific availability are out of scope.
- Weekday values use integers `0-6` to avoid locale-specific strings.
- Each barbershop can have at most one record per weekday.
- Owner/admin manage business hours; regular barbers do not.
- Platform admin support access follows existing tenant management convention.
- `opens_at` must be before `closes_at` when a day is open.
- Closed days are represented with `closed = true`; times may be null for closed days.
- UI is functional Blade only; polish remains deferred.
- `package-lock.json` remains intentionally local/uncommitted.
- `docs/next-session-handoff.md` remains local and must not be committed.

## Evidence
- RED: `php artisan test --compact tests/Feature/TenantBusinessHourManagementTest.php` failed before implementation with 10 errors: missing `App\\Models\\BusinessHour` and undefined `tenant.business-hours.*` routes.
- GREEN: `php artisan test --compact tests/Feature/TenantBusinessHourManagementTest.php` passed after implementation: 10 tests, 44 assertions.
- Formatting: `vendor/bin/pint --dirty --format agent` passed.
- Related tests: `php artisan test --compact tests/Feature/TenantServiceManagementTest.php` passed: 9 tests, 37 assertions; `php artisan test --compact tests/Feature/TenantBarberProfileManagementTest.php` passed: 9 tests, 38 assertions; `php artisan test --compact tests/Feature/TenantMembershipManagementTest.php` passed: 9 tests, 41 assertions; `php artisan test --compact tests/Feature/TenantDashboardFlowTest.php` passed: 11 tests, 31 assertions.
- Commits: none; parent/user requested no commit.
