# tenant-dashboard-flow

## Goal
Move BarberHub's authenticated landing flow from global `users.role` dashboards toward tenant-scoped dashboards based on active `memberships`.

## Approved scope
- Keep legacy `users.role` artifacts only where needed for compatibility; do not expand them as the final permission model.
- Platform admins use `users.is_platform_admin` and land on a platform dashboard.
- Users with one active membership land directly on that barbershop dashboard.
- Users with multiple active memberships land on a barbershop selector.
- Tenant dashboards use routes like `/barbershops/{barbershop:slug}/dashboard` protected by `auth`, `verified`, and `tenant.member`.
- Membership roles for MVP: `owner`, `admin`, `barber`.
- No Laravel Boost installation.
- No commit unless explicitly requested.

## Tasks
- [x] Add dashboard routing support for platform admin, single membership, and multiple memberships.
- [x] Add tenant dashboard and selector controllers/views/helpers.
- [x] Update login/registration/navigation to use tenant-aware destinations.
- [x] Add tests for platform admin, one membership, multiple memberships, role rendering, and cross-tenant denial.
- [x] Run focused and full verification.

## Evidence
- Worker GREEN: `php artisan test --filter=TenantDashboardFlowTest` passed: 10 tests, 29 assertions.
- Worker focused checks passed: `RoleDashboardTest`, `AuthenticationTest`, and `RegistrationTest`.
- Independent verifier initially found an intended-URL open redirect risk in login filtering; fixed by rejecting external intended hosts and redirecting only to local allowed dashboard paths.
- Final focused check: `php artisan test --filter=TenantDashboardFlowTest` passed: 11 tests, 31 assertions.
- Final full suite: `php artisan test` passed: 47 tests, 108 assertions.
- Final style/whitespace: `./vendor/bin/pint --test` passed and `git diff --check` passed.
- Native review/assessment unavailable because the package-local Gentle AI binary is missing (`package-local-binary-missing`); no lineage was created and no mutation was performed.
