# Initial owner/admin reports
Objective: deliver the six accepted operational reports. Branch: `feat/owner-reports` (from `main`).
Delivery: feature-branch-chain; local work-unit commits only, no push/PR authorization. Runner: `php artisan test --compact` (SQLite test database); PHP 8.5.4, Laravel 13.34.0.

## Specs
S1. "crea una rama nueva" — work on a new feature branch, preserving unrelated work.
S2. "haz los 6 reportes iniciales que me mencionaste" — accepted report 1: "Resumen de citas" (total, completed, cancelled, no-show, scheduled).
S3. "haz los 6 reportes iniciales que me mencionaste" — accepted reports 2–3: "Evolución de atenciones" (completed appointments and equal-length previous-period comparison), "Demanda por día y horario" (bookings by local weekday/hour).
S4. "haz los 6 reportes iniciales que me mencionaste" — accepted reports 4–6: "Servicios más realizados", "Actividad por barbero", "Clientes nuevos y recurrentes".
S5. "metelos en un grupo de la navbar llamado reportes" — expose all six reports in a Reportes navbar group for the barbershop owner/admin, desktop and mobile.

Implementation assumptions inherited from the accepted proposal: use the barbershop timezone and selected appointment-start period; completed appointments define atenciones and service popularity; first completed visit within this barbershop defines a new customer; returning customers have a completed visit before the selected period. Disclose appointments with no customer_id (not customers lacking a user account). Booked minutes are not actual work duration. No financial estimates, payment tracking, schema changes, dependency additions, or new design world. Reuse existing tenant access/module containment and UI conventions. Date/barber/service filters are tenant-scoped; show useful empty states. Default period and validation bounds will be recorded after inspecting the existing request conventions.
Risk: item 2 — new owner/admin report endpoints expose tenant business data and require server-side role and tenant isolation checks. Reuse existing guards without weakening them. One independent verification at feature closure.

## Tasks
T1 [S1,S2,S5] — delegated (context backstop): implement shared report filters/access/UI and appointment summary with tests; status: done; commit: recorded in L8 (first slice).
T2 [S3] — delegated, after T1 shared contracts: implement evolution and weekday/hour demand reports with tests; status: pending; commit: pending.
T3 [S4] — delegated, parallel with T2 on disjoint files: implement service, barber, and customer reports with tests; status: pending; commit: pending.
T4 [S1–S5] — inline integration plus independent verification: complete six-link navbar/routes, run focused/full tests, build and available browser checks, preserve unrelated work, close local slices; status: pending; commit: pending.

## Log
L1. User request verbatim: "Bien, crea una rama nueva y haz los 6 reportes iniciales que me mencionaste, metelos en un grupo de la navbar llamado reportes. Si necesitas hacerme preguntas adelante"
L2. User selected "Cadena sobre la rama de la funcionalidad — recomendado" (`feature-branch-chain`). Review workload forecast: approximately 1,200 authored changed lines including tests/views. Suggested local slices follow T1, T2, T3, and final integration; no publishing authorized.
L3. Branch `feat/owner-reports` created from `main`. Preexisting untracked `app/Http/Controllers/Http/` and `storage/` are outside task ownership and must not be staged, deleted, or reformatted.
L4. Read-only exploration confirmed existing appointments/services/customers and tenant dashboard; no historical price snapshot/payment records. Explorer mistakes corrected in planning: unidentified = nullable appointment customer_id, not nullable customer user_id; appointment interval = booked duration, not actual attendance.
L5. `gentle-ai review mode status`: off (clone-local and global); do not start native review or change mode. Dependencies confirmed using `composer show --direct`; no dependency changes planned.
L6. T1 worker stopped before edits because the actual tenant navigation is `resources/views/components/tenant-layout.blade.php`, outside the originally supplied surfaces. No RED/GREEN run. User explicitly selected "Autorizar el archivo del panel" (`approve-tenant-layout-surface`), authorizing that exact file for the Reportes group. Resume the same task with corrected surfaces; no feature-scope change.
L7. T1 RED: six tests failed for missing route before implementation. GREEN: `php artisan test --compact tests/Feature/TenantReportSummaryTest.php` passed 10 tests/52 assertions; parent spot-check repeated successfully. Existing `TenantDashboardFlowTest.php` passed 11 tests/31 assertions. Targeted Pint and diff-check passed. Date default = local calendar month through today, inclusive endpoints, maximum 366 days. Actual appointment storage uses naive local wall-clock datetimes (not UTC): preserve that convention. Reusable TenantReportData API: from/to/endExclusive/periodDays/timezone/barberId/serviceId/query/barbers/services. Native assess unavailable (`package-local-binary-missing`); explicit security escalation requires one verifier at closure; RDD remains off. Corrected cancellation copy to describe appointments scheduled within the period, not cancellation-event dates.
