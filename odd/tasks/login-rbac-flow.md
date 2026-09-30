# Login RBAC Flow

## Goal

Verify and tighten the Breeze login + RBAC flow for BarberHub: role-based login redirection, visible navigation, invalid roles, and unauthorized access behavior.

## Approved decisions

- Keep the simple `users.role` approach.
- Do not add Spatie Permission unless simple roles become insufficient.
- Keep visible UI and documentation in Spanish.
- Keep technical names in English where they are Laravel/project conventions.

## Scope

- Verify the current login and role dashboard flow on `main`.
- Add coverage for role-aware navigation and invalid role behavior.
- Preserve intended redirect behavior only when it does not send the user to a dashboard they cannot access.
- Ensure users with invalid roles do not silently fall back to the client dashboard.

## Non-goals

- No business modules.
- No permission matrix beyond role checks.
- No visual redesign beyond navigation clarity.
- No Spatie Permission package.

## Tasks

### 1. Map current login/RBAC behavior

Status: done

Outcome:
- Read Engram context and current repository state before modifying.
- Confirmed current helper defaults unknown roles to `client.dashboard`.
- Confirmed navigation only links to generic `/dashboard` and uses generic `Dashboard` label.
- Confirmed current tests cover happy-path role redirects and forbidden access, but not invalid roles or navigation visibility.

Validation:
- `php artisan test --filter=RoleDashboardTest` passed before changes.

Evidence:
- Current branch started from `main` at `084a268`.

### 2. Add focused RBAC flow coverage

Status: done

Outcome:
- Added tests for invalid-role `/dashboard` access, invalid-role login, cross-role intended dashboard login redirect, same-role intended dashboard preservation, and authenticated navigation dashboard link visibility.

Validation:
- `php artisan test --filter=RoleDashboardTest` initially failed on the new expectations before implementation.

Evidence:
- New failing assertions covered invalid role fallback, invalid-role login, cross-role intended redirect, and role-specific navigation links.

### 3. Tighten invalid-role and navigation behavior

Status: done

Outcome:
- Changed role dashboard resolution so unknown roles have no silent `client.dashboard` fallback.
- Made `/dashboard` forbid authenticated users without a valid role dashboard.
- Adjusted login redirect handling to ignore intended dashboards for other roles while preserving the intended dashboard for the authenticated user's own role.
- Updated navigation to show only the authenticated user's role dashboard link and Spanish label in desktop and responsive navigation.

Validation:
- `php artisan test --filter=RoleDashboardTest` passed.

Evidence:
- Role-specific dashboard route and label resolution now live in `App\Support\RoleDashboard`.

### 4. Validate work unit

Status: done

Outcome:
- Focused RBAC and affected Breeze auth coverage passed.

Validation:
- `php artisan test --filter=RoleDashboardTest` passed: 13 tests, 47 assertions.
- `php artisan test tests/Feature/Auth/AuthenticationTest.php tests/Feature/Auth/RegistrationTest.php` passed: 6 tests, 12 assertions.
- `php artisan test` passed: 40 tests, 110 assertions.
- `./vendor/bin/pint --test` passed.
- `npm run build` passed.
- `git diff --check` passed.

Evidence:
- No commit created.
