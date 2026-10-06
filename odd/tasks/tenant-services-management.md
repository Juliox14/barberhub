# Tenant services management

## Goal
Create tenant-scoped service catalog management so each barbershop can define services, prices, durations, and active state before appointments are implemented.

## Tasks
- [x] Preserve current local state before implementation.
  - Evidence: `git status --short` before edits showed `M package-lock.json`, `?? docs/next-session-handoff.md`, and `?? odd/tasks/tenant-services-management.md`; `package-lock.json` and `docs/next-session-handoff.md` were not edited.
- [x] Explore current tenant, route, model, factory, view, and test conventions.
  - Evidence: read `TenantBarberController`, `TenantMembershipController`, tenant routes, `Barbershop`, `Barber`, `Membership`, factories, tenant layout/dashboard, and related feature tests; CodeGraph was unavailable because the project is not indexed.
- [x] Add service schema/model/factory and relationships.
  - Evidence: added `database/migrations/2026_10_02_000006_create_services_table.php`, `app/Models/Service.php`, `database/factories/ServiceFactory.php`, and `Barbershop::services()`.
- [x] Implement tenant-scoped service management routes/controller/views.
  - Evidence: added `TenantServiceController`, `/barbershops/{barbershop:slug}/services` GET/POST/PUT routes, `tenant.services-index` view, tenant nav link, and dashboard link.
- [x] Add focused feature coverage for authorization, tenant isolation, duplicate names, validation, and active state.
  - Evidence: `php artisan test --compact tests/Feature/TenantServiceManagementTest.php` RED before implementation failed with missing `App\Models\Service` and `tenant.services.*` routes; after implementation passed with 9 tests / 37 assertions.
- [x] Run formatting and verification.
  - Evidence: `php artisan test --compact tests/Feature/TenantServiceManagementTest.php` passed with 9 tests / 37 assertions; `php artisan test --compact tests/Feature/TenantBarberProfileManagementTest.php tests/Feature/TenantMembershipManagementTest.php tests/Feature/TenantDashboardFlowTest.php` passed with 29 tests / 110 assertions; `vendor/bin/pint --dirty --format agent` passed; reran focused test after Pint and it passed with 9 tests / 37 assertions; independent verifier reran focused/related tests and `git diff --check` successfully.
- [x] Close the work unit according to repository policy.
  - Evidence: implementation complete without committing; `package-lock.json` and `docs/next-session-handoff.md` remain local/uncommitted.

## Scope decisions
- Services belong to exactly one barbershop.
- Owner/admin manage services; regular barbers do not.
- Platform admin support access follows existing tenant management convention.
- Service MVP fields: `name`, `description`, `price`, `duration_minutes`, `active`.
- Service names are unique per barbershop, not globally.
- UI is functional Blade only; polish remains deferred.
- Appointment availability rules are out of scope for this work unit.
- `package-lock.json` remains intentionally local/uncommitted.
- `docs/next-session-handoff.md` remains local and must not be committed.

## Evidence
- Commits: pending.
- RED: `php artisan test --compact tests/Feature/TenantServiceManagementTest.php` failed before implementation with missing `App\\Models\\Service` and undefined `tenant.services.*` routes.
- GREEN: `php artisan test --compact tests/Feature/TenantServiceManagementTest.php` passed after implementation: 9 tests, 37 assertions.
- Related: `php artisan test --compact tests/Feature/TenantBarberProfileManagementTest.php tests/Feature/TenantMembershipManagementTest.php tests/Feature/TenantDashboardFlowTest.php` passed: 29 tests, 110 assertions.
- Formatting: `vendor/bin/pint --dirty --format agent` passed.
- Independent verification: focused service tests passed (9 tests, 37 assertions); related tenant/barber/dashboard tests passed (29 tests, 110 assertions); `git diff --check` passed.
- Native review assessment/inspect: unavailable/blocked because the package-local Gentle AI binary is missing (`package-local-binary-missing`); no lineage was created, and separate verifier was run.
