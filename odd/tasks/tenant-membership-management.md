# Tenant membership management

## Goal
Allow barber shop owners/admins to manage members for their own barber shop, with server-side authorization and tenant isolation.

## Tasks
- [x] Close current pending commits before starting the work unit.
  - Evidence: `f6fa875 2026-10-05 ADD Laravel Boost tooling`; `7e655f8 2026-10-05 ADD platform barbershop management actions`.
- [x] Explore current tenant, membership, route, and test conventions.
  - Evidence: `Membership`, `EnsureTenantMembership`, `TenantDashboardFlowTest`, membership migration, routes inspected.
- [x] Implement membership management routes/controller/views.
  - Evidence: `TenantMembershipController` added with owner/admin/platform-admin checks, nested membership 404 guard, add/update/delete actions, last-active-owner protection; tenant member routes added under `/barbershops/{barbershop:slug}/members`; functional Spanish Blade screen added.
- [x] Add focused feature coverage for member listing, adding existing users, role/status updates, removal, last-owner protection, and cross-tenant/role denial.
  - Evidence: `tests/Feature/TenantMembershipManagementTest.php` covers owner/admin access, barber denial, other-tenant denial, add by email, duplicate update, update role/status, last-owner protection, cross-barbershop membership mutation 404, and platform admin support management.
- [x] Run formatting and verification.
  - Evidence: RED `php artisan test --compact tests/Feature/TenantMembershipManagementTest.php` failed before implementation because `tenant.members.*` routes were undefined; GREEN after implementation passed with 9 tests / 41 assertions; `vendor/bin/pint --dirty --format agent` passed; `php artisan route:list --name=tenant.members` showed 4 routes.
  - Evidence: parent verification passed `php artisan test --compact tests/Feature/TenantMembershipManagementTest.php tests/Feature/TenantDashboardFlowTest.php tests/Feature/PlatformBarbershopManagementTest.php` with 30 tests / 112 assertions, and `npm run build -- --outDir /tmp/barberhub-vite-build --emptyOutDir` passed.
  - Evidence: independent verifier reran membership tests, related tenant/platform tests, tenant member route list, and Vite build successfully.
- [ ] Commit the completed work unit.
  - Evidence: pending.

## Scope decisions
- MVP manages existing user accounts by email; no email invitations or password generation yet.
- Owners/admins can manage members; barbers cannot.
- Platform admins may access tenant-scoped management for support, following existing tenant bypass behavior.
- Do not polish UI beyond functional Blade screens; Codex can improve presentation later.

## Evidence
- Commits: pending for this work unit.
