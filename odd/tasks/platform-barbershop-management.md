# platform-barbershop-management

## Goal
Let a platform admin create and manage the first tenant records without Tinker: barbershops and their initial owner membership.

## Approved scope
- Only `users.is_platform_admin` users can access platform barbershop management.
- Add a simple platform barbershop index and create form.
- Create barbershops manually from the platform dashboard.
- Assign an initial owner by email.
- If the owner email does not exist, create a basic user account with a generated password/reset-required follow-up note or keep the MVP simpler by requiring an existing user email; implementation should choose the safest small MVP and document it.
- Slugs must be unique and server-validated.
- Keep UI simple and Spanish.
- No Laravel Boost installation.
- No commit unless explicitly requested.

## Tasks
- [x] Add platform admin authorization middleware or policy for platform-only routes.
- [x] Add routes/controllers/views for listing and creating barbershops.
- [x] Implement validated barbershop creation with initial owner membership.
- [x] Link platform dashboard to barbershop management.
- [x] Add tests for platform admin access, non-admin denial, creation success, validation, and initial owner membership.
- [x] Run focused and full verification.

## Evidence
- RED: `php artisan test tests/Feature/PlatformBarbershopManagementTest.php` failed before implementation because platform barbershop routes were undefined and the platform dashboard lacked the management link.
- GREEN: `php artisan test tests/Feature/PlatformBarbershopManagementTest.php` passed (6 tests, 21 assertions).
- Focused regression: `php artisan test tests/Feature/TenantDashboardFlowTest.php` passed (11 tests, 31 assertions).
- Full suite: `php artisan test` passed (53 tests, 129 assertions).
- Style/whitespace: `./vendor/bin/pint --test` passed and `git diff --check` passed.
- Independent verifier reran focused/full tests, Pint, and diff check; no blockers found in platform admin route authorization, owner email validation, membership creation, or UI routes.
- Native review/assessment unavailable because the package-local Gentle AI binary is missing (`package-local-binary-missing`); no lineage was created and no mutation was performed.
