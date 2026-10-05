# Super admin barbershop actions

## Goal
Add edit and delete actions to the super administrator barbershop management area.

## Tasks
- [x] Map current platform barbershop routes, controller, views, and tests.
  - Evidence: `routes/web.php`, `app/Http/Controllers/PlatformBarbershopController.php`, `resources/views/platform/barbershops-index.blade.php`, `tests/Feature/PlatformBarbershopManagementTest.php` read.
- [x] Implement edit/update/destroy routes, controller behavior, and UI actions.
  - Allowed edit surfaces: `routes/web.php`, `app/Http/Controllers/PlatformBarbershopController.php`, `resources/views/platform/barbershops-index.blade.php`, `resources/views/platform/barbershops-edit.blade.php`.
- [x] Add focused feature coverage for platform admin edit/delete and non-admin denial.
  - Allowed edit surfaces: `tests/Feature/PlatformBarbershopManagementTest.php`.
- [x] Run formatting and focused verification.
  - Evidence: `vendor/bin/pint --format agent routes/web.php app/Http/Controllers/PlatformBarbershopController.php tests/Feature/PlatformBarbershopManagementTest.php` passed.
  - Evidence: `php artisan test --compact tests/Feature/PlatformBarbershopManagementTest.php` passed after implementation with 10 tests / 40 assertions.
  - Evidence: `php artisan route:list --name=platform.barbershops --except-vendor` listed edit, update, and destroy routes.
  - Evidence: initial Vite build failed because Rolldown optional native bindings were missing; `npm install` restored optional packages, then `npm run build -- --outDir /tmp/barberhub-vite-build --emptyOutDir` passed.
  - Evidence: independent verifier reran focused tests, route list, and Vite build successfully.

## Notes
- Laravel Boost was installed per project instructions before application changes; this added project tooling files and dependency updates.
- `.ai/rules` does not exist, so no path-scoped rule files applied.
- Boost MCP tools were not available in this Pi MCP session; fallback to local Artisan/file inspection.

## Evidence
- Commits: not committed yet; user requested implementation, not an explicit commit for this task.
