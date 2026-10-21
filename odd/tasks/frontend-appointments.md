# Fase 2 — Agenda interna de citas
Branch: `frontend-visual-foundation` · Scope: agenda tenant mobile-first y gestión interna de citas; reutiliza la fundación visual de Fase 1.
Intent: entregar el primer corte visual/funcional previsto para Fase 2 sin iniciar reservas públicas ni alterar reglas de negocio.

## Specs

S1. User request (verbatim): “Continúa el desarrollo de la interfaz a partir del sistema visual compartido implementado durante la Fase 1. Reutilizar los tokens CSS, componentes Blade y layouts desarrollados en la Fase 1. Mantener la consistencia visual con las referencias de BarberHub. Respetar la arquitectura Laravel, los permisos, roles y el aislamiento multi-tenant. Evitar duplicaciones y componentes innecesarios. Mantener la interfaz responsiva, accesible y consistente. Conservar el idioma español en toda la interfaz y documentación institucional del proyecto. No inventes requisitos ni rediseñes componentes que ya fueron aprobados.”
S2. Producto: la primera entrega Fase 2 es gestión interna de citas por barbería, no reserva pública. Mostrar una lista de citas por fecha y permitir a owner/admin crear una cita seleccionando barbero activo, servicio activo, cliente opcional y hora de inicio. Reutilizar `AppointmentScheduler` para validaciones y duración; ningún dato elegido debe poder cruzar barberías.
S3. Decisión del usuario: “Yo creo que sería bueno que los dos tengan prioridad, pero más que nada el de barberos que normalmente será accedido por dispositivos moviles habría que hacerlo con enfoque mobile first, tambien pensando en la posterior adaptación a PWA”. La interfaz es responsive, prioriza el uso móvil por barberos y no implementa funcionalidades PWA en este corte.
S4. Decisiones de presentación del usuario: lista por fecha como vista primaria; usar las referencias visuales existentes como criterio de densidad; reutilizar sidebar/topbar y componentes tenant de Fase 1.
S5. Acceso: incluir agenda propia para barberos (sólo sus citas de la barbería activa); owner/admin ven y crean citas del local. El servidor aplica permisos y aislamiento; la interfaz no sustituye autorización.
S6. No añadir calendario, reserva pública, pagos, notificaciones, estados/acciones no solicitados, campos de métricas ni dependencias. Mantener referencias `UI_REFERENCES/` intactas.

## Tasks
- T1 [S1-S6] Implementar rutas/controlador/vistas de agenda tenant, creación owner/admin, consulta propia de barbero y navegación mobile-first, con pruebas de aislamiento/autorización; route: parent inline; commit: `fce49ecf7b5719d870acc30c02b04b45a53a61ec` (`feat(appointments): add tenant agenda and scheduling form`); complete.
- T2 [S1-S6] Ejecutar verificación enfocada, regresiones pertinentes, Pint, build, diff check y revisión independiente; registrar pruebas y límites de evidencia; route: parent inline; commit: `fce49ecf7b5719d870acc30c02b04b45a53a61ec`; complete.

## Log
- L1 User request (verbatim): “Quiero retomar el desarrollo de BarberHub y comenzar la Fase 2 de la interfaz de usuario, utilizando la metodología ODD de Gentle AI.

1. Recuperación del contexto

Antes de modificar cualquier archivo:

Recupera las memorias, decisiones arquitectónicas, preferencias y sesiones anteriores de BarberHub utilizando Engram.

Lee docs/next-session-handoff.md, si existe.

Consulta odd/tasks/frontend-visual-foundation.md y los demás registros ODD relevantes.

Inspecciona el estado actual de Git, la rama activa y los cambios pendientes.

Identifica qué se implementó realmente durante la Fase 1 y si quedó formalmente cerrada.

Consulta la documentación del proyecto para determinar el alcance previsto de la Fase 2.

No presupongas que las tareas están completadas únicamente por aparecer implementadas.

2. Entorno de desarrollo

El proyecto fue migrado de Windows al sistema de archivos nativo de WSL.

Directorio: /home/juliox/proyectos/BarberHub

Laravel y PHP se ejecutan desde WSL.

Vite y Node.js se ejecutan desde WSL.

PostgreSQL 16 se ejecuta mediante Docker.

La aplicación se consulta desde Windows en http://127.0.0.1:8000/.

Gentle AI, Pi y Engram están configurados en WSL.

No utilices el antiguo repositorio de /mnt/c/proyectos/BarberHub.

No inicies servidores duplicados ni modifiques la base de datos sin necesidad.

3. Objetivo: Fase 2 de la UI

Continúa el desarrollo de la interfaz a partir del sistema visual compartido implementado durante la Fase 1.

Debes:

Identificar el alcance exacto de la Fase 2 a partir de las tareas, referencias visuales y decisiones previas.

Reutilizar los tokens CSS, componentes Blade y layouts desarrollados en la Fase 1.

Mantener la consistencia visual con las referencias de BarberHub.

Respetar la arquitectura Laravel, los permisos, roles y el aislamiento multi-tenant.

Evitar duplicaciones y componentes innecesarios.

Mantener la interfaz responsiva, accesible y consistente.

Conservar el idioma español en toda la interfaz y documentación institucional del proyecto.

No inventes requisitos ni rediseñes componentes que ya fueron aprobados.”
- L2 Recuperación: `docs/next-session-handoff.md` recomienda agenda interna tenant como primer corte: lista por fecha y creación para owner/admin; explícitamente excluye booking público y otras funciones. Fase 1 fue cerrada formalmente por decisión explícita del usuario y commits `7fe9258` (fundación visual) y `241e071` (evidencia ODD). Quedaron documentados límites de verificación visual/interactiva del navegador y ASSESS, aceptados para el cierre.
- L3 Referencias existentes: `UI_REFERENCES/Dashboard-Owner-Admin.png`, `Dashboard-Barbero.png`, `Dashboard-Super-Admin.png`, `Login.png`; no modificar. La captura de barbero muestra agenda propia móvil como uso posterior previsto.
- L4 Entorno actualizado por el usuario en esta sesión: PHP/Laravel, Node/Vite y PostgreSQL Docker corren en WSL; consultar desde Windows en `http://127.0.0.1:8000/`. Nunca usar `/mnt/c`, iniciar servidores duplicados ni alterar DB innecesariamente. Esto reemplaza la preferencia Engram antigua de ejecutar PHP/npm desde Windows.
- L5 Decisiones confirmadas: vista primaria lista por fecha; responsive con prioridad mobile-first por uso de barberos y futura adaptación PWA, sin implementar PWA ahora; densidad según referencias; añadir agenda propia de barberos (sólo sus citas); owner/admin ven y crean para la barbería. Sin calendario ni booking público en esta entrega.
- L6 Git al comenzar: rama `frontend-visual-foundation`, commits más recientes `241e071` y `7fe9258`; cambios tracked limpios. `.codegraph/` y `UI_REFERENCES/` están sin track y pertenecen al entorno/referencias: no tocar, añadir ni eliminar.
- L7 Exploration: `EnsureTenantMembership` sólo verifica membresía activa, así que control por rol debe residir también en controladores. `AppointmentScheduler` ya aplica tenant, actividad, granularidad de 15 minutos, horarios, disponibilidad y solapamientos. `tenant-layout` aún muestra Agenda como próximamente; las rutas bajo el slug son tenant-scoped.
- L8 Verificación: no correr migraciones ni conectar a PostgreSQL para cambios de UI si SQLite `:memory:` de PHPUnit es suficiente. Correr `npm run build` desde WSL según el entorno actualizado del usuario; no levantar servidor, y no declarar pruebas de navegador si no hay herramienta disponible.
- L9 Implementación y verificación local: `TenantAppointmentController` agrega lectura por fecha y creación validada con `AppointmentScheduler`; barberos sólo consultan su agenda vinculada a su usuario, y owner/admin crean. Las rutas permanecen en grupo `tenant.member`; claves barber/service/customer se validan contra la barbería actual. La vista reutiliza `x-tenant-layout` y `x-ui.surface`, en español y responsive, y la agenda es un enlace activo del shell. Prueba RED observada: ruta `tenant.appointments.index` inexistente. Prueba GREEN actual: `php artisan test --compact tests/Feature/TenantAppointmentManagementTest.php tests/Feature/AppointmentFoundationTest.php tests/Feature/TenantServiceManagementTest.php tests/Feature/TenantDashboardFlowTest.php` — 35 tests, 119 assertions. `vendor/bin/pint --dirty --format agent`, `npm run build` (Vite 8.3.3) y `git diff --check` pasan. No se ejecutaron migraciones, servidor ni cambios a PostgreSQL.
- L10 Verificación independiente `gentle-ai-verify`: dictaminó S1-S6 conformes en el alcance inspeccionado; ocho pruebas focalizadas, 35 aserciones, y `git diff --check` pasaron. No encontró hallazgos de seguridad altos/críticos. Browser responsive/visual y densidad comparada con referencias no fueron verificadas; la integridad del directorio de referencias no se inspeccionó. El contrato de appointments almacena timestamps `dateTime` sin zona horaria; un test con `America/Mexico_City` demostró que la UI conserva y presenta la hora local almacenada (no convertir el wall time como UTC). Se agregaron pruebas explícitas de cliente de otro tenant y POST sin membresía activa tras el primer review. Verificación local final: slice de 35 tests, 119 assertions; Pint, Vite 8.3.3 build y diff check pasan. Revisión independiente del candidato final dictaminó S1-S6 conformes, con la limitación de pruebas visuales de navegador documentada.
- L11 Evidencia de ejecución: pruebas HTTP de feature cubren la interfaz y persistencia con PHPUnit usando SQLite `:memory:`; no se iniciaron servidores ni se conectó a PostgreSQL. Verificación real en navegador no disponible. Límite de rollback: retirar `TenantAppointmentController`, rutas `tenant.appointments.*`, vista `appointments-index`, pruebas `TenantAppointmentManagementTest` y enlace Agenda de `tenant-layout`; no requiere migración ni altera appointment foundation. No push.
- L12 Cierre del work unit: commit `fce49ecf7b5719d870acc30c02b04b45a53a61ec` (`feat(appointments): add tenant agenda and scheduling form`) en rama `frontend-appointments`; contiene comportamiento, pruebas y registro ODD. Es un candidato local para revisión; no se publicó. Diff de 562 líneas añadidas/eliminadas en seis archivos, por lo que conviene mantener este corte enfocado si luego se prepara un PR. Working tree tracked limpio tras el commit; `.codegraph/` y `UI_REFERENCES/` siguen untracked y sin modificar.
