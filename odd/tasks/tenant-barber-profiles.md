# Tenant barber profiles

## Goal
Create tenant-scoped barber profiles that identify which active barbershop members are schedulable professionals, while keeping tenant authorization and scheduling identity separate.

## Tasks
- [x] Preserve current local state before implementation.
  - Evidence: pre-existing `M package-lock.json` is intentionally kept from Windows-side `npm install`; `?? docs/next-session-handoff.md` remains local/uncommitted; neither file was edited by this work unit.
- [x] Explore current tenant, membership, route, model, factory, and test conventions.
  - Evidence: read `TenantMembershipController`, `routes/web.php`, `Barbershop`, `Membership`, `User`, membership/user/barbershop factories, `TenantMembershipManagementTest`, tenant Blade views, project skills, and Laravel 13 docs through Boost `search-docs`.
- [x] Add barber profile schema/model/factory and relationships.
  - Evidence: added `app/Models/Barber.php`, `database/factories/BarberFactory.php`, `database/migrations/2026_10_02_000005_create_barbers_table.php`; added `Barbershop::barbers()` and `User::barberProfiles()`.
- [x] Implement tenant-scoped barber profile management routes/controller/views.
  - Evidence: added `TenantBarberController`, `tenant.barbers.*` routes under `/barbershops/{barbershop:slug}/barbers`, `resources/views/tenant/barbers-index.blade.php`, and dashboard link to barber management.
- [x] Add focused feature coverage for authorization, tenant isolation, duplicates, and inactive membership behavior.
  - Evidence: `php artisan test --compact tests/Feature/TenantBarberProfileManagementTest.php` before implementation failed RED with missing `App\Models\Barber` and `tenant.barbers.*` routes; final run passed with 9 tests / 38 assertions.
- [x] Run formatting and verification.
  - Evidence: `php artisan test --compact tests/Feature/TenantBarberProfileManagementTest.php` passed (9 tests, 38 assertions); `php artisan test --compact tests/Feature/TenantMembershipManagementTest.php tests/Feature/TenantDashboardFlowTest.php tests/Feature/RoleDashboardTest.php` passed (22 tests, 75 assertions); `vendor/bin/pint --dirty --format agent` passed; independent verifier reran focused/related tests and `git diff --check` successfully.
- [x] Close the work unit according to repository policy.
  - Evidence: committed as `f29159c 2026-10-06 ADD barber profile foundation` and `8c14f1e 2026-10-06 ADD tenant barber profile management`; `docs/next-session-handoff.md` and `package-lock.json` remain local/uncommitted.

## Scope decisions
- Barber profiles are operational/schedulable records, not membership authorization records.
- Owner/admin manage barber profiles; regular barbers do not.
- A barber profile can only be created for an existing active membership in the same barbershop.
- Platform admin access should follow existing tenant support behavior unless exploration proves the current convention differs.
- UI is functional Blade only; polish is intentionally deferred.
- The user wants to keep current `package-lock.json` changes from Windows-side `npm install`.
- `docs/next-session-handoff.md` remains local and must not be committed.

## Evidence
- Commits: `f29159c 2026-10-06 ADD barber profile foundation`; `8c14f1e 2026-10-06 ADD tenant barber profile management`.
- RED: `php artisan test --compact tests/Feature/TenantBarberProfileManagementTest.php` failed before implementation: 9 errors, including missing `App\Models\Barber` and undefined `tenant.barbers.*` routes.
- GREEN: `php artisan test --compact tests/Feature/TenantBarberProfileManagementTest.php` passed after implementation: 9 tests, 38 assertions.
- Related: `php artisan test --compact tests/Feature/TenantMembershipManagementTest.php tests/Feature/TenantDashboardFlowTest.php tests/Feature/RoleDashboardTest.php` passed: 22 tests, 75 assertions.
- Formatting: `vendor/bin/pint --dirty --format agent` passed.
- Independent verification: `php artisan test --compact tests/Feature/TenantBarberProfileManagementTest.php` passed (9 tests, 38 assertions); related tenant/dashboard/RBAC tests passed (22 tests, 75 assertions); `git diff --check` passed.
- Native review assessment/inspect: unavailable/blocked because the package-local Gentle AI binary is missing (`package-local-binary-missing`); no lineage was created, and separate verifier was run.
