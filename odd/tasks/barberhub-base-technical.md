# BarberHub Base Technical Phase

## Goal

Prepare the technical foundation for BarberHub before implementing business modules.

## Approved decisions

- Run PHP and Laravel in WSL.
- Run PostgreSQL in Docker.
- Use Laravel Breeze with Blade for authentication.
- Represent initial roles with a simple `role` column on `users`.
- Build the technical base before barber shop modules.
- Skip Laravel Boost for now, despite the generated `AGENTS.md` suggestion, to keep dependencies minimal.
- Use Spanish for UI, documentation, academic-facing texts, and demo content; keep English for technical names where Laravel/database conventions make it appropriate.

## Constraints

- Keep the project simple and aligned with Laravel conventions.
- Do not add unnecessary dependencies.
- Do not implement all modules at once.
- Do not introduce abstractions without a concrete need.
- Use `BARBERHUB_CONTEXT.md` as the project source of truth.
- Do not commit unless the user explicitly authorizes commits.

## Tasks

### 1. Create ODD task record

Status: done

Outcome:
- Track the initial technical phase before source writes.

Validation:
- This file exists and reflects the approved decisions.

Evidence:
- Created `odd/tasks/barberhub-base-technical.md`.
- Mirrored the task context in Engram under `odd/barberhub-base-technical/tasks`.

### 2. Initialize Git without commits

Status: done

Outcome:
- Created the local Git repository metadata.
- Renamed the initial branch to `main`.
- Did not commit because commits require explicit user authorization.

Validation:
- `git status --short --branch` works and reports `## No commits yet on main`.

Evidence:
- `git init`
- `git branch -m main`
- `git status --short --branch`

### 3. Add PostgreSQL Docker Compose configuration

Status: done

Outcome:
- Added a minimal PostgreSQL 16 service for local development.
- Used development-only credentials: database `barberhub`, user `barberhub`, password `barberhub`.
- Added a named volume and PostgreSQL healthcheck.

Validation:
- Initially blocked because Docker was unavailable.
- After environment setup, `docker compose config --quiet` passed.
- Container startup remains blocked by Docker socket permissions.

Evidence:
- Created `docker-compose.yml`.
- `docker compose config: ok`.
- `docker ps` fails with Docker socket permission denied.

### 4. Prepare PHP/Composer next step

Status: done

Outcome:
- Confirmed the initial blocker: PHP and Docker were missing in WSL.
- User installed/enabled the required tools.
- Verified PHP, Composer, Docker, Docker Compose, Node, npm, and Git are now available.

Validation:
- `php -v`: PHP 8.5.4.
- `composer --version`: Composer 2.9.5.
- `docker --version`: Docker 29.8.0.
- `docker compose version`: Docker Compose v5.5.1.
- `node --version`: v24.21.0.
- `npm --version`: 12.1.0.
- `git --version`: 2.53.0.

Evidence:
- Environment validation command passed after user setup.

### 5. Create Laravel application skeleton

Status: done

Outcome:
- Created a Laravel 13 skeleton through a temporary directory and copied it into the project root while preserving `BARBERHUB_CONTEXT.md`, `odd/`, Git metadata, and `docker-compose.yml`.
- Reinstalled Composer dependencies from `composer.lock` after an interrupted copy left `vendor/` incomplete.
- Created local `.env` from `.env.example`, configured app name and PostgreSQL development connection, and generated the application key.
- Laravel now runs locally.
- Merged Laravel `.gitignore` rules with local Pi runtime ignore rules.

Validation:
- `composer install --no-interaction` completed.
- `php artisan key:generate --ansi --force` completed.
- `php artisan --version` reports Laravel Framework 13.34.0.
- `.env` non-sensitive DB keys point to PostgreSQL database `barberhub`.

Evidence:
- `composer create-project laravel/laravel /tmp/barberhub-laravel --no-interaction` installed `laravel/laravel` v13.10.1.
- `php artisan --version`: Laravel Framework 13.34.0.

### 6. Install Breeze with Blade

Status: done

Outcome:
- Installed Laravel Breeze as a development dependency.
- Generated Breeze Blade authentication scaffolding.
- Installed Node dependencies and built frontend assets through the Breeze installer.

Validation:
- `composer require laravel/breeze --dev --no-interaction` completed.
- `php artisan breeze:install blade --no-interaction` completed.
- Breeze installer reported successful scaffolding and Vite production build.

Evidence:
- `laravel/breeze` v2.4.2 installed.
- `npm install` completed through Breeze installer.
- Vite build completed through Breeze installer.

### 7. Add simple user roles

Status: done

Outcome:
- Added a `role` column to `users` with default `client`.
- Allowed `role` mass assignment on `App\Models\User`.
- Added focused feature tests for default role and role persistence.

Validation:
- `php artisan test` passed: 27 tests, 27 passed, 63 assertions.

Evidence:
- Created `database/migrations/2026_09_30_071608_add_role_to_users_table.php`.
- Updated `app/Models/User.php`.
- Created `tests/Feature/UserRoleTest.php`.

### 8. Validate PostgreSQL runtime migration

Status: done

Outcome:
- Development `.env` is configured for PostgreSQL.
- PostgreSQL container starts through Docker Compose.
- Laravel migrations run successfully against PostgreSQL.
- Feature/unit test suite remains green after PostgreSQL validation.

Validation:
- `docker ps` works without sudo after the user fixed Docker access.
- `docker compose up -d postgres` completed.
- `php artisan migrate --force` completed against PostgreSQL.
- `php artisan test` passed.

Evidence:
- PostgreSQL image `postgres:16-alpine` pulled and `barberhub-postgres` started.
- Migrations applied: users, cache, jobs, and `add_role_to_users_table`.
- Test result: 27 tests, 27 passed, 63 assertions.

### 9. Decide agent skills versioning

Status: done

Outcome:
- Keep `.agents/` because the user intentionally prepared project-relevant skills.
- Keep `skills-lock.json` because it pins sources and hashes for those skills.
- Do not treat `skills-lock.json` as a Laravel default file; it belongs to the agent skills setup.

Validation:
- `.agents/skills` contains Laravel/Eloquent/PHP/Tailwind/design/security skills relevant to BarberHub.
- `.atl/skill-registry.md` indexes those skills as project-scoped skills.

Evidence:
- `.agents/skills/eloquent-best-practices/SKILL.md`
- `.agents/skills/laravel-specialist/SKILL.md`
- `.agents/skills/php-best-practices/SKILL.md`
- `.agents/skills/tailwindcss-development/SKILL.md`
- `.agents/skills/frontend-design/SKILL.md`
- `.agents/skills/hallmark/SKILL.md`
- `.agents/skills/owasp-top-10-testing/SKILL.md`
- `skills-lock.json`

### 10. Apply Spanish-first project language convention

Status: done

Outcome:
- Set the application default locale to Spanish.
- Set the example environment locale values to Spanish.
- Added Spanish JSON translations for the Breeze authentication/profile UI strings.
- Replaced the default Laravel welcome page with a simple BarberHub academic/project landing page in Spanish.
- Kept technical names and Laravel conventions in English where appropriate.

Validation:
- `php artisan config:clear --ansi` completed.
- `php artisan test` passed: 27 tests, 27 passed, 63 assertions.
- `npm run build` completed successfully.

Evidence:
- Updated `config/app.php`.
- Updated `.env.example` and local `.env` locale values.
- Created `lang/es.json`.
- Updated `resources/views/welcome.blade.php`.
- Updated layout title fallbacks in `resources/views/layouts/app.blade.php` and `resources/views/layouts/guest.blade.php`.
