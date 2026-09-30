# Contexto agéntico de BarberHub

Este documento permite continuar BarberHub desde otra máquina aunque Engram no esté sincronizado. Usalo como punto de entrada para configurar una laptop y reconstruir el contexto del proyecto antes de pedir cambios a un agente.

## Ruta rápida en otra máquina

1. Cloná el repositorio o actualizá tu copia local.
2. Leé primero estos archivos:
   - `BARBERHUB_CONTEXT.md`
   - `AGENTS.md`
   - `docs/agentic-handoff.md`
   - `odd/tasks/*.md` cuando existan
3. Pedile al agente:

```text
Continuemos BarberHub. Antes de modificar, leé BARBERHUB_CONTEXT.md, AGENTS.md, docs/agentic-handoff.md, odd/tasks/*.md si existen, Engram si está disponible, y revisá git status.
```

## Estado técnico conocido

| Área | Decisión actual |
| --- | --- |
| Framework | Laravel + Blade |
| Auth | Laravel Breeze |
| Base de datos | PostgreSQL en Docker para desarrollo |
| Roles | Columna `users.role` |
| Roles válidos | `admin`, `barber`, `client` |
| RBAC | Middleware propio `role:*` |
| Dashboards | `/admin/dashboard`, `/barber/dashboard`, `/client/dashboard` |
| Helper de dashboard | `App\Support\RoleDashboard` |
| Middleware de rol | `App\Http\Middleware\EnsureUserHasRole` |
| Paquete Spatie | No usar salvo que aparezca una necesidad real de permisos finos |
| UI/documentación | Español |
| Nombres técnicos | Inglés cuando corresponda a Laravel, PHP, BD o convenciones del proyecto |

## Reglas para agentes

Antes de modificar código:

1. Revisar `git status --short --branch`.
2. Leer `BARBERHUB_CONTEXT.md`.
3. Leer `AGENTS.md`.
4. Revisar `odd/tasks/*.md` si existen.
5. Consultar Engram si está disponible, pero no depender de Engram como única fuente de verdad.
6. Identificar la rama activa y confirmar que corresponde al trabajo solicitado.

Durante el trabajo:

- Mantener cambios pequeños y verificables.
- No introducir Spatie Permission mientras `users.role` sea suficiente.
- No agregar dependencias sin justificación.
- Mantener UI visible y documentación en español.
- Mantener clases, métodos, rutas internas y nombres técnicos en inglés cuando sea lo natural.
- Evitar subagentes background largos salvo autorización explícita o necesidad clara.
- Preferir exploración inline acotada o subagentes en modo task con alcance mínimo.

Después del trabajo:

- Ejecutar pruebas relevantes.
- Registrar decisiones importantes en Engram si está disponible.
- Actualizar archivos `odd/tasks/*.md` cuando la tarea sea sustancial.
- No hacer push, PR ni merge sin autorización explícita.

## Contexto recuperado desde Engram

Engram en una laptop nueva puede estar vacío si no comparte el mismo backend. Estas son las memorias clave que conviene preservar en el repo:

### Base Laravel/Breeze/roles

- Se creó una base Laravel con Breeze Blade.
- Se agregó `users.role` con default `client`.
- Se mantienen roles simples porque el alcance inicial sólo requiere `admin`, `barber` y `client`.
- Breeze y textos visibles se adaptaron al enfoque español primero.

### RBAC básico

- Se implementó RBAC simple sin Spatie.
- `/dashboard` actúa como redirector por rol.
- Hay dashboards separados por rol.
- `EnsureUserHasRole` protege cada dashboard.
- `RoleDashboard` centraliza la resolución del dashboard del usuario.
- Tests principales: `tests/Feature/RoleDashboardTest.php`.

### Política de subagentes

Un subagente `gentle-ai-explore` quedó activo demasiado tiempo y consumió usage. Para este proyecto:

- No lanzar exploradores background largos sin necesidad explícita.
- Si se lanza un background task, cancelarlo apenas deje de aportar valor.
- Para tareas chicas de Laravel/RBAC, preferir lectura inline acotada.

## Trabajo reciente conocido

### Rama `main`

Contiene la base Laravel + Breeze + roles simples y RBAC básico con dashboards separados por rol.

Commit relevante:

```text
084a268 feat: add role based dashboards
```

### Rama `feature/login-rbac-flow`

En la sesión donde se creó este documento, existía trabajo local no commiteado para ajustar el flujo login/RBAC:

- Roles inválidos ya no deberían caer silenciosamente al panel cliente.
- `/dashboard` debería devolver `403` para usuarios autenticados con rol inválido.
- Login debería ignorar intended dashboards de otros roles.
- Login debería preservar intended dashboards del mismo rol.
- La navegación debería mostrar sólo el dashboard del rol autenticado.
- Tests enfocados pasaron localmente.

Validaciones reportadas para ese trabajo local:

```text
php artisan test --filter=RoleDashboardTest       # 13 tests, 47 assertions
php artisan test                                  # 40 tests, 110 assertions
./vendor/bin/pint --test                         # passed
npm run build                                    # passed
git diff --check                                 # passed
```

Si esa rama no aparece en la laptop, primero hay que pushearla desde la máquina original o recrear los cambios desde el resumen anterior.

## Setup local recomendado

Desde una copia limpia:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Para PostgreSQL local con Docker:

```bash
docker compose up -d
php artisan migrate
```

Para desarrollo frontend:

```bash
npm run dev
```

Validación base:

```bash
php artisan test
./vendor/bin/pint --test
npm run build
```

## Prompt recomendado para nueva sesión

```text
Continuemos BarberHub desde esta máquina. Revisá Engram si está disponible, pero usá el repo como fuente portable. Antes de modificar, leé BARBERHUB_CONTEXT.md, AGENTS.md, docs/agentic-handoff.md y odd/tasks/*.md si existen. Revisá git status y la rama activa. Mantené UI/documentación en español, nombres técnicos en inglés cuando corresponda, RBAC simple con users.role y sin Spatie salvo necesidad real. No lances subagentes background largos sin avisarme.
```

## Checklist para mover trabajo entre dispositivos

- [ ] La rama de trabajo está commiteada.
- [ ] La rama fue pusheada al remoto.
- [ ] Los archivos `odd/tasks/*.md` relevantes están versionados.
- [ ] La laptop tiene PHP, Composer, Node, npm y Docker funcionando.
- [ ] El agente de la laptop leyó este documento antes de modificar.
