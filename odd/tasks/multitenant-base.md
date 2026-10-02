# multitenant-base

## Goal
Build the first BarberHub multi-tenant foundation work unit: barbershops, memberships, customers without required users, and server-side membership access checks.

## Approved decisions
- `customers.user_id` is nullable so a barber shop can manage customers without registered accounts.
- `memberships` stores a user's belonging and role inside a barbershop.
- Membership roles for the MVP: `owner`, `admin`, `barber`.
- `users.is_platform_admin` is a boolean for the MVP.
- Barbershops are created manually by platform admins.
- Tenant routes use a barbershop slug/identifier plus middleware that validates membership.
- Cross-tenant integrity is enforced in Laravel through validation, policies/middleware, and tests.
- `customer_preferences` is 1:1 for the MVP.

## Tasks
- [x] Add base schema for platform admin flag, barbershops, memberships, customers, and customer preferences.
- [x] Add Eloquent models, relationships, casts, and factories for the base tenant entities.
- [x] Add tenant membership middleware and register it for route use.
- [x] Add tests for nullable customer users, membership access, platform admin access, and cross-tenant denial.
- [x] Run focused and full relevant verification.

## Evidence
- RED: `php artisan test tests/Feature/MultitenantFoundationTest.php` failed before implementation because the new tenant models and `users.is_platform_admin` column did not exist.
- GREEN: `php artisan test tests/Feature/MultitenantFoundationTest.php` passed with 7 tests and 12 assertions after implementation.
- Full suite: `php artisan test` passed with 47 tests and 122 assertions in independent verification.
- Style: `./vendor/bin/pint --test` passed after formatting the new feature test and again in independent verification.
- Diff whitespace: `git diff --check` failed only on pre-existing/user-owned `BARBERHUB_CONTEXT.md` trailing whitespace at lines 389-390; implementation files had no reported whitespace errors.
- Native review/assessment: unavailable because the package-local Gentle AI binary is missing (`package-local-binary-missing`); no lineage was created and no mutation was performed.

## Notes
- Do not install Laravel Boost for now; user explicitly declined it.
- Existing `users.role` RBAC remains transitional and should not be expanded as the final multi-tenant permission model.
- No commit will be created unless the user explicitly asks for it.
