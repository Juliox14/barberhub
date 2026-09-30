# RBAC Role Dashboards

## Goal

Implement simple role-based access control for BarberHub using the existing `users.role` column and separate dashboards for administrator, barber, and client roles.

## Approved decisions

- Keep the implementation simple.
- Use separate dashboards by role.
- Do not add Spatie Permission unless the simple approach becomes insufficient.
- Keep UI text in Spanish while preserving technical names in English where appropriate.

## Scope

- Add role access middleware.
- Add dashboard routes for `admin`, `barber`, and `client`.
- Redirect authenticated users to the dashboard matching their role.
- Provide minimal Blade views that show the dashboard and role.
- Add tests for allowed/forbidden access and role-based login and registration redirection.

## Non-goals

- No visual design beyond minimal readable pages.
- No permission matrix beyond role checks.
- No business modules yet.
- No Spatie Permission package unless a blocker appears.

## Tasks

### 1. Create ODD task record

Status: done

Outcome:
- Tracked the RBAC role dashboard work before source changes.

Validation:
- This file exists and reflects the approved scope.

Evidence:
- `odd/tasks/rbac-role-dashboards.md` updated with implementation and validation evidence.

### 2. Map current Breeze routing and auth flow

Status: done

Outcome:
- Identified existing `/dashboard` route and Breeze login/registration redirects to the generic `dashboard` route.

Validation:
- Read `routes/web.php`, `AuthenticatedSessionController`, `RegisteredUserController`, existing dashboard view, and auth tests before implementation.

Evidence:
- Existing `/dashboard` route was an authenticated/verified Blade view before this change.
- Breeze login and registration redirected to `route('dashboard', absolute: false)` before this change.

### 3. Implement role middleware and dashboards

Status: done

Outcome:
- Added `role` middleware backed by `App\Http\Middleware\EnsureUserHasRole`.
- Registered the middleware alias in `bootstrap/app.php` using Laravel 13 middleware configuration.
- Added protected `admin`, `barber`, and `client` dashboard routes.
- Kept `/dashboard` as an authenticated redirector using `App\Support\RoleDashboard`.
- Updated login and registration redirects to the role-specific dashboard.
- Explicitly assigns newly registered users the `client` role.
- Added minimal Spanish dashboard views.

Validation:
- `php artisan route:list --path=dashboard` lists all four dashboard routes.

Evidence:
- Route list includes `admin.dashboard`, `barber.dashboard`, `client.dashboard`, and `dashboard`.

### 4. Add RBAC tests

Status: done

Outcome:
- Added feature coverage for allowed role access, forbidden access, generic dashboard redirect, login redirects by role, and registration redirect/default client role.
- Updated existing Breeze auth tests for the new client-dashboard redirect behavior.

Validation:
- `php artisan test` passes.

Evidence:
- RED: focused dashboard/auth test command initially failed with 404s and missing role dashboard route names.
- GREEN: focused dashboard/auth test command passed with 10 tests and 33 assertions.
- Full suite: `php artisan test` passed with 34 tests and 89 assertions.

### 5. Validate and review work unit

Status: done

Outcome:
- Local validation passed after review adjustments.
- Visible dashboard text was adjusted to Spanish-first wording: `Panel de administración`, `Panel de barbero`, and `Panel de cliente`.
- Login now preserves Laravel's intended redirect behavior with the role dashboard as the fallback.
- Native assessment could not classify untracked changes and requested independent verification.
- Two read-only verifier tasks were launched but canceled after taking too long; no verifier mutations were reported.
- Final decision used full local validation plus manual diff review before commit.

Validation:
- `php artisan test` passed: 35 tests, 35 passed, 93 assertions.
- `php artisan route:list --path=dashboard` lists `admin.dashboard`, `barber.dashboard`, `client.dashboard`, and `dashboard`.
- `npm run build` completed successfully.
- `./vendor/bin/pint --test` passed.
- `git diff --check` passed.

Evidence:
- Added middleware `App\\Http\\Middleware\\EnsureUserHasRole`.
- Added helper `App\\Support\\RoleDashboard`.
- Updated Breeze login and registration redirects.
- Added minimal Spanish dashboard views.
- Added `tests/Feature/RoleDashboardTest.php` and updated auth expectations.
