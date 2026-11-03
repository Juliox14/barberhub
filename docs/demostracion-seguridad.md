# Demostración de seguridad — BarberHub

> **Propósito de este documento**
> Este archivo es un *briefing/instructivo* para que un asistente (por ejemplo Gemini, con acceso a tu Google Docs) redacte el informe final de la actividad de **bloques de seguridad**. Aquí está mapeado cada requerimiento del enunciado con su **implementación real en BarberHub**, las **rutas/archivos** que lo soportan, **marcadores de captura de pantalla** (📸) y los **pasos exactos para reproducir y probar** cada apartado.
>
> Los bloques marcados con 🧩 **PENDIENTE** están preparados para completarse después de su implementación (no hay captura todavía).

---

## 0. Resumen ejecutivo de adaptación

BarberHub es una aplicación Laravel (PHP 8.5, Laravel 13) multitenant de gestión de barberías. El enunciado pide demostrar cómo se adaptó cada requerimiento de seguridad. La tabla resume el estado:

| # | Requerimiento del enunciado | Estado en BarberHub | Adaptación clave |
|---|---|---|---|
| 1.1 | Login seguro con validación de credenciales | ✅ Implementado | Breeze + validación genérica de errores + sesión regenerada |
| 1.2 | Control de acceso basado en roles | ✅ Implementado | Roles `platform admin / owner / admin / barber / client` |
| 2.1 | Bloqueo automático de cuentas | ✅ Implementado | 10 fallos / 15 min → bloqueo 15 min (por cuenta, no por IP) |
| 2.2 | Restricción temporal de módulos | ✅ Implementado | Suspensión por barbería y módulo (15 min), con levantar anticipado |
| 2.3 | Cierre/invalidación de sesiones sospechosas | ✅ Implementado | Revocación por admin + sesión por dispositivo + sondeo |
| 2.4 | Registro de acciones en bitácora | ✅ Implementado | Bitácora con lista blanca de eventos + auto-refresco |
| 2.5 | Notificación al responsable de seguridad | ✅ Implementado | Alertas persistentes + correo a destinatario configurable |
| 3.1 | Contraseñas seguras con expiración | ✅ Implementado | 90 días, restricción a renovación |
| 3.2 | Políticas de complejidad | ✅ Implementado | Mín. 12, mayúscula, minúscula, número y símbolo |
| 3.3 | Cifrado o hash seguro | ✅ Implementado | bcrypt + secretos cifrados/ocultos |
| 3.4 | Verificación en dos pasos (2FA) | ✅ Implementado | TOTP (app autenticadora) + códigos de recuperación |
| 4.1 | CAPTCHA y reCAPTCHA | 🧩 Pendiente | Aprobado por el docente, aún fuera de alcance |
| 4.2 | Protección contra fuerza bruta | ✅ Implementado | Rate limiting (5/email+IP) + bloqueo de cuenta |
| 5.1 | APIs seguras (OAuth 2.0, JWT) | ✅ Adaptado | Sesiones seguras de Laravel (aceptado por el docente) |
| 5.2 | Gestión segura de sesiones | ✅ Implementado | Sesión por dispositivo, cookie `http_only`, `same_site=lax` |
| 5.3 | Protección contra inyección SQL | ✅ Implementado | Eloquent/Query Builder con binding de parámetros |

---

## 1. Control de acceso y autenticación

### 1.1 Login seguro con validación de credenciales

**Qué se implementó**
- Login con validación de credenciales (correo + contraseña) a través del controlador de Breeze (`AuthenticatedSessionController`).
- Errores **genéricos** de credenciales: no se revela si falló el correo o la contraseña (evita enumeración de cuentas).
- La contraseña se verifica contra el hash almacenado (bcrypt), nunca contra texto plano.
- La sesión se regenera al autenticar y se restablecen los tokens de sesión.
- Si la cuenta tiene 2FA activo, el login **no completa** el acceso: redirige al reto TOTP (ver §3.4).

**Archivos clave**
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Requests/Auth/LoginRequest.php`
- `app/Support/SingleDeviceSession.php`

📸 **[CAPTURA 1.1a]** — Pantalla `/login`. Mostrar: formulario "Correo electrónico" + "Contraseña" + botón "Iniciar sesión".

📸 **[CAPTURA 1.1b]** — Intento fallido. Mostrar: mensaje genérico de error (sin indicar qué campo es incorrecto).

**Pasos para reproducir y probar**
1. Levantar la app (`php artisan serve` o `npm run dev` + servidor) y abrir `/login`.
2. Capturar la pantalla de login (1.1a).
3. Escribir un correo existente con contraseña incorrecta y enviar.
4. Capturar el mensaje genérico de error (1.1b): no dice "el correo no existe" ni "la contraseña es incorrecta" por separado.
5. Iniciar sesión con credenciales correctas y verificar que se redirige al panel según el rol (ver §1.2).

---

### 1.2 Control de acceso basado en roles

**Qué se implementó**
BarberHub adapta el ejemplo genérico del enunciado (Administrador / Alumno / Profesor) a su dominio de barberías. Se usan **tres niveles** de identidad:

| Ejemplo del enunciado | Rol equivalente en BarberHub | Alcance de acceso |
|---|---|---|
| Administrador: acceso completo | **Administrador de plataforma** (`is_platform_admin`) | Acceso completo: panel de plataforma, barberías, seguridad (contención, bitácora, alertas), revocación de sesiones |
| — (nivel intermedio del dominio) | **Dueño / Administrador de barbería** (`memberships.role = owner/admin`) | Gestiona su barbería: equipo, servicios, horarios, citas, disponibilidad |
| Profesor: acceso a materias asignadas | **Barbero** (`memberships.role = barber`) | Accede a su agenda, disponibilidad y **citas asignadas** |
| Alumno: acceso a su información | **Cliente** (`users.role = client`) | Accede a su **información y citas** |

**Cómo se controla**
- Columna global `users.role` + bandera `is_platform_admin` + rol por membresía `memberships.role` (`owner`, `admin`, `barber`).
- Middleware `EnsureUserHasRole` (`app/Http/Middleware/EnsureUserHasRole.php`) que devuelve **403** si el rol no coincide.
- Resolución de panel por rol en `app/Support/TenantDashboard.php` (platform → barbería → selector de barberías).
- Rutas de plataforma protegidas con el middleware `platform.admin` (ver `routes/web.php`).

**Archivos clave**
- `app/Http/Middleware/EnsureUserHasRole.php`
- `app/Support/TenantDashboard.php`
- `app/Models/Membership.php` (constantes `ROLE_OWNER`, `ROLE_ADMIN`, `ROLE_BARBER`)
- `routes/web.php` (grupo `platform.*`)

📸 **[CAPTURA 1.2a]** — Panel del administrador de plataforma. Mostrar: menú completo con "Seguridad", "Barberías", etc.

📸 **[CAPTURA 1.2b]** — Panel de un barbero (rol `barber`). Mostrar: solo su agenda/disponibilidad/citas asignadas, sin opciones de plataforma.

📸 **[CAPTURA 1.2c]** — Acceso denegado (403). Mostrar: un barbero o cliente intentando abrir `/platform/security` y recibiendo el error 403.

**Pasos para reproducir y probar**
1. Iniciar sesión como **administrador de plataforma** y capturar el panel completo (1.2a).
2. Iniciar sesión como **barbero** de una barbería y capturar su panel limitado (1.2b).
3. Con el barbero (o cliente) autenticado, abrir manualmente `/platform/security` y capturar el 403 (1.2c).
4. (Opcional) Iniciar sesión como **cliente** y mostrar que solo ve su información y citas.

---

## 2. Mecanismos de contención

### 2.1 Bloqueo automático de cuentas tras múltiples intentos fallidos

**Qué se implementó**
- Política: **10 intentos fallidos por cuenta en 15 minutos → bloqueo de nuevos ingresos durante 15 minutos**, aunque cambie la IP.
- Los intentos durante el bloqueo **no prolongan** su duración.
- Con la cuenta bloqueada, ni siquiera la contraseña correcta permite entrar.
- Mensaje específico de bloqueo temporal: *"intente más tarde o pruebe 'olvidaste tu contraseña'"* (`auth.temporarily_blocked`).
- La **recuperación de contraseña sigue disponible** y las sesiones legítimas activas **no se cierran** por fallos externos.

**Archivos clave**
- `app/Support/AccountLoginGuard.php` (umbral, ventana, duración, bloqueo por cuenta)
- `app/Http/Requests/Auth/LoginRequest.php` (mensajes de bloqueo y rate limit)
- `app/Events/AccountLoginLocked.php` + listener de bitácora/alertas

📸 **[CAPTURA 2.1a]** — Mensaje de bloqueo. Mostrar: el aviso "acceso bloqueado temporalmente… intente más tarde o use 'olvidaste tu contraseña'".

📸 **[CAPTURA 2.1b]** — Bitácora mostrando el evento `security.account_locked` / `login.failed` asociados al bloqueo (ver §2.4).

**Pasos para reproducir y probar**
1. Elegir una cuenta existente y, en `/login`, enviar **10 veces** una contraseña incorrecta (dentro de 15 minutos).
2. En el 10º intento capturar el mensaje de bloqueo temporal (2.1a).
3. Intentar de nuevo **con la contraseña correcta** durante el bloqueo: debe seguir bloqueado (misma advertencia).
4. Verificar que el enlace "olvidaste tu contraseña" sigue funcionando (envío de recuperación).
5. (Opcional) Abrir la bitácora como admin de plataforma y capturar el evento (2.1b).
6. Esperar 15 minutos (o verificarlo con los tests automatizados, que viajan en el tiempo) y comprobar el desbloqueo automático.

---

### 2.2 Restricción temporal de acceso a módulos afectados

**Qué se implementó**
- El **administrador de plataforma** puede suspender un **módulo de una barbería** (por ejemplo, *citas*, *miembros*, *barberos*, *servicios*, *horarios*, *disponibilidad*) durante **15 minutos**, dejando un motivo.
- La restricción es **por barbería y por módulo**: no afecta a otras barberías ni a las herramientas de recuperación/seguridad.
- Se puede **levantar antes** del plazo, también con motivo.
- Tanto la suspensión como el levantamiento quedan **registrados en la bitácora** y generan **alerta**.

**Archivos clave**
- `app/Support/ModuleContainment.php`
- `app/Models/ModuleContainment.php`
- `app/Http/Controllers/PlatformSecurityController.php` (acciones `suspendModule` / `releaseModule`)
- `app/Http/Middleware/EnsureModuleAvailable.php`
- Rutas en `routes/web.php` (`security.modules.suspend` / `security.modules.release`)

📸 **[CAPTURA 2.2a]** — Formulario de contención. Mostrar: selección de barbería, módulo y motivo, en "Seguridad → Contención".

📸 **[CAPTURA 2.2b]** — Efecto de la suspensión. Mostrar: un usuario de esa barbería intentando acceder al módulo suspendido (acceso restringido) mientras otro módulo de la misma barbería sigue funcionando.

**Pasos para reproducir y probar**
1. Como **admin de plataforma**, abrir **Seguridad → Contención** y capturar el formulario (2.2a).
2. Suspender el módulo **citas** de una barbería concreta (motivo de ejemplo, p. ej. "incidente reportado").
3. Como dueño/barbero de **esa barbería**, intentar entrar a citas: debe estar restringido (2.2b).
4. Verificar que **otro módulo** de la misma barbería (p. ej. servicios) y **otra barbería** siguen accesibles.
5. Volver como admin y **levantar** la restricción antes del plazo, dejando motivo.
6. (Opcional) Confirmar en la bitácora los eventos `platform.barbershop.module_suspended` y `module_released`.

---

### 2.3 Cierre o invalidación de sesiones sospechosas

**Qué se implementó**
- El **administrador de plataforma** puede revocar la sesión de una cuenta, dejando **motivo** y registro.
- La revocación invalida la **propiedad de sesión**, los **tokens "recuérdame"** y las suscripciones push de ese dispositivo.
- **Un cambio de IP por sí solo no se considera prueba de compromiso** (no dispara revocación automática).
- Sesión por dispositivo: si la misma cuenta inicia sesión en otro navegador/dispositivo, aparece un **modal de advertencia** para *cambiar la sesión a ese dispositivo* o *salir* (preservando o reemplazando la sesión anterior).
- Un **sondeo** comprueba el estado de la sesión cada 30 segundos (solo con la pestaña visible); al confirmarse la revocación, redirige automáticamente al login con aviso.

**Archivos clave**
- `app/Support/SingleDeviceSession.php`
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php` (confirmación/cancelación de transferencia)
- `app/Http/Controllers/Auth/SessionStatusController.php` (`session.status`)
- `app/Http/Controllers/PlatformSecurityController.php` (revocación)
- `resources/js/session-monitor.js` + `resources/views/components/session-monitor.blade.php`

📸 **[CAPTURA 2.3a]** — Modal de conflicto de sesión. Mostrar: aviso al iniciar en un segundo dispositivo con opciones "cambiar a este dispositivo" / "salir".

📸 **[CAPTURA 2.3b]** — Revocación desde plataforma. Mostrar: formulario "Seguridad → Sesiones" con motivo de revocación.

📸 **[CAPTURA 2.3c]** — Redirección a login. Mostrar: el dispositivo revocado vuelve a `/login?session=revoked` con el aviso correspondiente.

**Pasos para reproducir y probar**
1. Iniciar sesión con una cuenta en el **navegador A**.
2. Abrir un **navegador B / incógnito** e iniciar sesión con la **misma cuenta**: capturar el modal de conflicto (2.3a).
3. Confirmar "cambiar a este dispositivo" y verificar que A queda sin acceso (o cancelar y verificar que A se conserva).
4. Como **admin de plataforma**, abrir **Seguridad → Sesiones**, elegir la cuenta, escribir un motivo y revocar (2.3b).
5. En el dispositivo de la cuenta revocada, esperar ≤30 s (o recargar): debe redirigir al login con aviso (2.3c).

---

### 2.4 Registro de acciones en bitácora

**Qué se implementó**
- Bitácora de seguridad con **lista blanca de eventos** (nunca se registran datos arbitrarios ni credenciales/tokens).
- Eventos registrados: `login.succeeded`, `login.failed`, `login.locked_out`, `logout`, `password.updated`, `password.reset`, eventos de 2FA, `security.account_locked`, `security.session_revoked`, `platform.barbershop.module_suspended/released`, creación/edición/borrado de barberías y de membresías.
- Cada registro muestra **nombre y correo legibles** del actor/cuenta, la **barbería** a la que pertenece, además de los ids numéricos secundarios, IP y user-agent.
- **Solo el administrador de plataforma** puede consultarla; el resto recibe 403.
- **Auto-refresco cada 15 segundos** sin recargar la página (con pausa en pestañas ocultas y tolerancia a fallos).
- Las salidas están escapadas (protección XSS) y no se guardan contraseñas, tokens ni secretos.

**Archivos clave**
- `app/Support/SecurityAuditLogger.php` (lista blanca de eventos y metadatos)
- `app/Models/SecurityAuditLog.php`
- `app/Http/Controllers/SecurityAuditLogController.php`
- `resources/js/security-audit-poller.js` (auto-refresco)
- Ruta `security-audit-logs.index` en `routes/web.php`

📸 **[CAPTURA 2.4a]** — Bitácora. Mostrar: tabla con actor (nombre + correo), cuenta/sujeto, barbería, evento, IP, fecha.

📸 **[CAPTURA 2.4b]** — Acceso denegado. Mostrar: un usuario no-admin recibiendo 403 al abrir `/platform/security-audit-logs`.

**Pasos para reproducir y probar**
1. Generar algunos eventos (login correcto, login fallido, un bloqueo del §2.1, una suspensión del §2.2).
2. Como **admin de plataforma**, abrir **Bitácora** (`/platform/security-audit-logs`) y capturar la tabla (2.4a).
3. Verificar que se ven nombre y correo del actor y la barbería, sin secretos.
4. Con una cuenta no-admin, abrir la misma ruta y capturar el 403 (2.4b).
5. (Opcional) Dejar la bitácora abierta, generar un evento en **otro navegador** y comprobar que aparece en ≤15 s sin recargar.

---

### 2.5 Notificación al responsable de seguridad

**Qué se implementó**
- **Alertas persistentes** visibles solo para administradores de plataforma (en "Seguridad → Alertas").
- **Correo electrónico** a un responsable configurable mediante la variable `SECURITY_ALERT_RECIPIENT`.
- **Deduplicación** de alertas repetidas en una ventana de 15 minutos (agrupación por evento/sujeto/módulo) para evitar spam.
- La entrega de correo está **aislada**: si falla, no rompe la acción principal ni la bitácora.
- ⚠️ **Caveat honesto**: en el entorno de desarrollo el correo usa el transporte `log` (no se demuestra entrega real a una bandeja). Para entrega real hay que configurar un driver de correo (SMTP, etc.) y un worker de colas.

**Archivos clave**
- `app/Support/SecurityAlertRecorder.php`
- `config/security.php` (`alert_recipient` desde `SECURITY_ALERT_RECIPIENT`)
- `app/Http/Controllers/PlatformSecurityController.php` (vista de alertas)

📸 **[CAPTURA 2.5a]** — Panel de alertas. Mostrar: lista persistente de alertas agrupadas con su tipo, sujeto y fecha.

📸 **[CAPTURA 2.5b]** — (Opcional, si se configura correo real) Captura del correo recibido por el responsable.

**Pasos para reproducir y probar**
1. Configurar `SECURITY_ALERT_RECIPIENT` en `.env` (p. ej. `correo@dominio.com`).
2. Disparar un evento de contención (p. ej. el bloqueo del §2.1 o la suspensión del §2.2).
3. Como admin de plataforma, abrir **Seguridad → Alertas** y capturar la alerta persistente (2.5a).
4. (Opcional, con correo real) Verificar la bandeja del responsable y capturar el correo (2.5b).

---

## 3. Gestión de credenciales

### 3.1 Contraseñas seguras con expiración

**Qué se implementó**
- Vida útil de contraseña de **90 días** (`PasswordPolicy::PASSWORD_LIFETIME_DAYS = 90`).
- Al vencer, el usuario queda **restringido a renovación, recuperación y cierre de sesión**; no puede usar el resto de la aplicación.
- Las cuentas existentes cuentan desde la **activación de la política** (baseline de la migración), **no** desde su fecha de creación histórica.
- Ruta de renovación: `/password/renew`.

**Archivos clave**
- `app/Support/PasswordPolicy.php`
- `app/Providers/PasswordPolicyServiceProvider.php` (registro del gate de expiración)
- `app/Http/Middleware/EnsurePasswordCurrent.php`
- `app/Http/Controllers/Auth/PasswordRenewalController.php`
- `routes/password-policy.php`
- `resources/views/auth/password-renewal.blade.php`

📸 **[CAPTURA 3.1a]** — Pantalla de renovación. Mostrar: usuario con contraseña vencida redirigido a `/password/renew`, sin acceso al resto de la app.

**Pasos para reproducir y probar**
1. (Demo manual) Poner la columna `password_changed_at` de un usuario a una fecha de hace >90 días.
2. Iniciar sesión con ese usuario: debe quedar restringido a `/password/renew` (3.1a).
3. Intentar navegar a cualquier módulo y verificar que redirige a renovación.
4. Renovar la contraseña y comprobar que se recupera el acceso.
5. (Alternativa determinista) Ejecutar los tests `PasswordPolicyTest` que viajan en el tiempo y cubren el límite exacto de 90 días.

---

### 3.2 Políticas de complejidad

**Qué se implementó**
- Política: **mínimo 12 caracteres**, al menos una **mayúscula**, una **minúscula**, un **número** y un **símbolo**.
- Se aplica en: **registro**, **invitaciones** (de barbero y de dueño), **recuperación** y **cambio de contraseña**.
- Mensajes de validación en español que explican los requisitos.
- Componente visual que muestra en vivo qué requisitos se cumplen (feedback de contraseña).

**Archivos clave**
- `app/Providers/PasswordPolicyServiceProvider.php` (regla de validación central)
- `app/Observers/PasswordCredentialObserver.php`
- `app/Support/PasswordPolicy.php`
- `lang/es/validation.php` (mensajes de complejidad)
- `resources/views/components/password-requirements.blade.php`
- `resources/js/password-feedback.js`

📸 **[CAPTURA 3.2a]** — Error de complejidad. Mostrar: registro/cambio con contraseña débil y el mensaje de requisitos (12, mayúscula, minúscula, número, símbolo).

📸 **[CAPTURA 3.2b]** — Feedback en vivo. Mostrar: el checklist de requisitos marcándose conforme se escribe una contraseña válida.

**Pasos para reproducir y probar**
1. Abrir `/register` (o "Cambiar contraseña" en el perfil).
2. Escribir una contraseña débil (p. ej. `password`) y enviar: capturar el error (3.2a).
3. Escribir una contraseña que cumpla todo (p. ej. `MiClaveSegura123!`) y capturar el checklist en vivo (3.2b).
4. Verificar que se acepta el registro/cambio con la contraseña fuerte.

---

### 3.3 Cifrado o hash seguro

**Qué se implementó**
- Las contraseñas se guardan con **bcrypt** mediante el cast `'password' => 'hashed'` del modelo `User`.
- Los **secretos de 2FA** se guardan **cifrados** (`encrypted`) y los **códigos de recuperación** como **hash** (nunca en texto plano).
- Atributos secretos **ocultos** en la serialización: `password`, `remember_token`, `two_factor_secret`, `two_factor_pending_secret`, `two_factor_recovery_codes`.
- Ninguna contraseña, token o secreto se registra en **logs**, **respuestas** ni en la **bitácora**.

**Archivos clave**
- `app/Models/User.php` (casts `hashed`, `encrypted`, `array` + `#[Hidden]`)

📸 **[CAPTURA 3.3a]** — Hash en base de datos. Mostrar: la columna `password` de `users` con un valor `$2y$…` (bcrypt), nunca texto plano.

**Pasos para reproducir y probar**
1. Registrar o actualizar la contraseña de un usuario.
2. Inspeccionar la tabla `users` (p. ej. con una herramienta de base de datos) y capturar el valor hash de `password` (3.3a).
3. Verificar que no hay contraseña en texto plano en ninguna columna ni en la bitácora.

---

### 3.4 Verificación en dos pasos (2FA)

**Qué se implementó**
- **TOTP con app autenticadora** (Google Authenticator, Authy, etc.) usando `pragmarx/google2fa`.
- El **código QR se genera localmente** (con `bacon/bacon-qr-code`); los secretos **nunca** se envían a terceros.
- **Códigos de recuperación de un solo uso** (almacenados como hash; se muestran una única vez).
- Para activar 2FA hay que **confirmar un código TOTP válido** antes.
- **Obligatorio para roles privilegiados** (plataforma, owners y admins de barbería) con **gracia de 14 días**; opcional para barberos y clientes.
- Durante la gracia se muestra aviso; al vencer, los roles obligatorios sin 2FA quedan restringidos a configuración/cierre.
- Protección contra **replay/expiración** (un paso TOTP no se reutiliza), **rate limiting** del reto, y uso atómico de los códigos de recuperación.
- Las **sesiones existentes y "recuérdame" no pueden saltarse** el segundo factor.

**Archivos clave**
- `app/Support/TwoFactorAuthentication.php` (enrolamiento, verificación, códigos)
- `app/Support/TwoFactorLogin.php` (reto de login)
- `app/Http/Controllers/Auth/TwoFactorEnrollmentController.php`
- `app/Http/Controllers/Auth/TwoFactorChallengeController.php`
- `app/Http/Middleware/EnsureTwoFactor.php`
- `routes/two-factor.php`
- `resources/views/auth/two-factor-*.blade.php` + `resources/views/components/account-security-layout.blade.php`

📸 **[CAPTURA 3.4a]** — Pantalla de configuración 2FA. Mostrar: "Seguridad de mi cuenta → Verificación en dos pasos" con el botón de activar.

📸 **[CAPTURA 3.4b]** — QR de enrolamiento. Mostrar: el QR local para escanear con la app autenticadora + campo de código.

📸 **[CAPTURA 3.4c]** — Códigos de recuperación. Mostrar: la lista de códigos de un solo uso (a guardar).

📸 **[CAPTURA 3.4d]** — Reto al iniciar sesión. Mostrar: pantalla de código TOTP tras ingresar credenciales correctas.

📸 **[CAPTURA 3.4e]** — Aviso de gracia (roles privilegiados). Mostrar: el aviso de 14 días para completar el enrolamiento obligatorio.

**Pasos para reproducir y probar**
1. Como **usuario privilegiado** (p. ej. admin de plataforma), abrir **Seguridad de mi cuenta → Verificación en dos pasos** (3.4a).
2. Pulsar "Activar": aparece el **QR local** (3.4b). Escanearlo con una app autenticadora.
3. Ingresar el código TOTP generado para **confirmar** el enrolamiento.
4. Se muestran los **códigos de recuperación** de un solo uso: guardarlos y capturarlos (3.4c).
5. Cerrar sesión y volver a iniciar: tras las credenciales correctas aparece el **reto TOTP** (3.4d); sin el código no se entra.
6. Probar un código de recuperación (opción "usar código de recuperación") y verificar que es de **un solo uso** (el segundo intento con el mismo código falla).
7. Verificar el **aviso de gracia de 14 días** en un rol privilegiado aún sin 2FA (3.4e).

---

## 4. Protección contra ataques

### 4.1 Implementación de CAPTCHA y reCAPTCHA

🧩 **PENDIENTE — bloque preparado para completar tras su implementación**

- **Estado**: no implementado en la rama actual. El docente aceptó reCAPTCHA como mecanismo válido, pero quedó fuera del alcance de los bloques implementados hasta ahora.
- **Dónde se integrará** (referencia para completar): formulario de `/login` y/o `/register`, antes del envío de credenciales.

📸 **[CAPTURA 4.1a]** — *(pendiente)* Widget de reCAPTCHA visible en el login/registro.

**Pasos para reproducir y probar** — *(a completar tras la implementación)*
1. _(pendiente)_ Verificar que el formulario no envía credenciales sin resolver el reCAPTCHA.
2. _(pendiente)_ Verificar que un intento sin resolver el reto es rechazado.

---

### 4.2 Protección contra ataques de fuerza bruta

**Qué se implementó**
Dos capas complementarias:
1. **Rate limiting por correo + IP** de Laravel (`RateLimiter`): **5 intentos fallidos** por clave `email|ip` → pausa con mensaje `auth.throttle` (segundos restantes).
2. **Bloqueo por cuenta** (`AccountLoginGuard`): **10 intentos fallidos por cuenta en 15 min → bloqueo de 15 min**, independiente de la IP (ver §2.1).
- Además, los endpoints de 2FA tienen su propio rate limiting (`throttle:20,1` en el reto, `throttle:10,1` en el enrolamiento).

**Archivos clave**
- `app/Support/AccountLoginGuard.php`
- `app/Http/Requests/Auth/LoginRequest.php` (`ensureIsNotRateLimited`, `throttleKey`)
- `routes/two-factor.php` (throttles)

📸 **[CAPTURA 4.2a]** — Mensaje de rate limit. Mostrar: aviso de "demasiados intentos, reintente en N segundos".

**Pasos para reproducir y probar**
1. En `/login`, enviar 5 veces credenciales incorrectas para el mismo correo desde la misma IP: capturar el aviso de reintento (4.2a).
2. Continuar hasta 10 intentos y verificar el bloqueo por cuenta del §2.1 (independiente de la IP).

---

## 5. Seguridad en servicios y sesiones

### 5.1 Uso de APIs seguras (OAuth 2.0, JWT)

**Qué se implementó (adaptación)**
- BarberHub **no usa OAuth 2.0 ni JWT**: el docente **aceptó** la adaptación a **sesiones seguras de Laravel**.
- Configuración de sesión aplicada: driver `database`, cookie **`http_only`** (no accesible desde JavaScript), **`same_site=lax`** (mitiga CSRF), `secure` según entorno (HTTPS en producción) y **regeneración de sesión** al autenticar.
- Las APIs internas usan el guard de sesión web + protección CSRF en formularios.

**Archivos clave**
- `config/session.php` (`driver`, `http_only`, `same_site`, `secure`, `encrypt`)
- `config/auth.php` (guard `web` por sesión)

📸 **[CAPTURA 5.1a]** — Evidencia de configuración. Mostrar: fragmento de `config/session.php` con `http_only => true` y `same_site => 'lax'`, o las cookies de sesión en DevTools con flags `HttpOnly`/`SameSite=Lax`.

**Pasos para reproducir y probar**
1. Abrir la app, iniciar sesión y abrir las **DevTools → Application → Cookies**.
2. Capturar la cookie de sesión mostrando los flags `HttpOnly` y `SameSite=Lax` (5.1a).

---

### 5.2 Gestión segura de sesiones

**Qué se implementó**
- **Sesión por dispositivo** (una cuenta, un dispositivo a la vez) con modal de transferencia/cancelación.
- **Revocación** de sesiones por el administrador de plataforma, con motivo (ver §2.3).
- **Rotación** de tokens "recuérdame" y **propiedad de sesión** con versionado (`security_session_version`).
- **Sondeo** de estado cada 30 s en pestañas visibles; redirección automática al login al confirmarse la revocación; los fallos de red **no** se tratan como revocación.
- Nota de arquitectura registrada: si en el futuro se implementa WebSockets, se reutilizará para estas notificaciones.

**Archivos clave**
- `app/Support/SingleDeviceSession.php`
- `app/Http/Controllers/Auth/SessionStatusController.php`
- `resources/js/session-monitor.js` + `resources/views/components/session-monitor.blade.php`
- Rutas `session.status`, `login.session-conflict.confirm/cancel` en `routes/auth.php`

📸 **[CAPTURA 5.2a]** — Estado de sesión. Mostrar: usuario autenticado en un dispositivo y, al abrir en otro, el modal de conflicto (reutilizable de 2.3a).

**Pasos para reproducir y probar**
1. Repetir los pasos del §2.3 (conflicto de dispositivo, transferencia y revocación).
2. Verificar que, tras la revocación, el dispositivo sale solo al login en ≤30 s o al recargar.

---

### 5.3 Protección contra inyección SQL

**Qué se implementó**
- Toda consulta a base de datos usa **Eloquent ORM / Query Builder con binding de parámetros** (prepared statements), no SQL crudo concatenado.
- Las consultas que requieren integridad usan **transacciones** y **`lockForUpdate`** (bloqueos de fila), p. ej. en `AccountLoginGuard`, `ModuleContainment`, `SingleDeviceSession` y `TwoFactorAuthentication`.
- Los identificadores de ruta se resuelven mediante *route model binding* de Laravel (no se interpolan manualmente).

**Archivos clave**
- `app/Support/AccountLoginGuard.php`, `app/Support/ModuleContainment.php`, `app/Support/SingleDeviceSession.php`, `app/Support/TwoFactorAuthentication.php`
- `app/Models/*.php` (Eloquent)

📸 **[CAPTURA 5.3a]** — Evidencia de código. Mostrar: un fragmento de un modelo/consulta Eloquent con binding de parámetros (p. ej. `User::query()->where('email', $email)->lockForUpdate()->first()`).

**Pasos para reproducir y probar**
1. Abrir uno de los archivos de `app/Support/` y capturar la consulta Eloquent con binding (5.3a).
2. (Opcional) Verificar con los tests que las entradas maliciosas no alteran el comportamiento de las consultas.

---

## 6. Cambios incluidos de la rama actual (`feat/security-block-3`)

La rama actual aporta el **bloque de credenciales**: políticas de contraseña + verificación en dos pasos, integradas con los bloques previos (bitácora, contención y sesiones). Los bloques anteriores (bitácora, contención/alertas, sesión por dispositivo) ya están integrados en el historial (commits previos) y se describen arriba como parte del sistema completo.

**Áreas nuevas/modificadas en la rama actual**

| Área | Archivos representativos |
|---|---|
| Política de contraseña (complejidad + expiración) | `app/Support/PasswordPolicy.php`, `app/Observers/PasswordCredentialObserver.php`, `app/Providers/PasswordPolicyServiceProvider.php`, `app/Http/Middleware/EnsurePasswordCurrent.php`, `app/Http/Controllers/Auth/PasswordRenewalController.php` |
| 2FA (TOTP) | `app/Support/TwoFactorAuthentication.php`, `app/Support/TwoFactorLogin.php`, `app/Http/Controllers/Auth/TwoFactorEnrollmentController.php`, `app/Http/Controllers/Auth/TwoFactorChallengeController.php`, `app/Http/Middleware/EnsureTwoFactor.php` |
| Migraciones | `database/migrations/2026_10_31_011852_add_two_factor_authentication_to_users_table.php`, `database/migrations/2026_10_31_012057_add_password_policy_to_users_table.php` |
| Rutas | `routes/password-policy.php`, `routes/two-factor.php` (nuevas) |
| Vistas/UI | `resources/views/auth/password-renewal.blade.php`, `resources/views/auth/two-factor-*.blade.php`, `resources/views/components/password-requirements.blade.php`, `resources/views/components/account-security-layout.blade.php`, `resources/views/components/account-settings-menu.blade.php`, `resources/views/components/credential-security-notice.blade.php` |
| Integración de login/sesión | `app/Http/Controllers/Auth/AuthenticatedSessionController.php`, `app/Http/Controllers/Auth/SessionStatusController.php`, `app/Http/Requests/Auth/LoginRequest.php`, `resources/js/session-monitor.js` |
| Traducciones | `lang/es/validation.php` |
| Tests | `tests/Feature/PasswordPolicyTest.php`, `tests/Feature/TwoFactorEnrollmentTest.php`, `tests/Feature/TwoFactorLoginTest.php`, `tests/Feature/PasswordFeedbackTest.php`, `tests/Feature/AccountSettingsMenuTest.php`, `tests/JavaScript/password-feedback.test.js`, y actualizaciones de `Auth/*`, `SecurityAuditLogTest.php`, `session-monitor.test.mjs`, etc. |

**Notas de entrega de la rama**
- Los tests están **verificados localmente** con SQLite aislado (401 tests PHP / 2960 aserciones + 50 tests Node + build de producción, todos en verde), y con una verificación independiente sin bloqueos. No se reclama prueba de navegador real, correo real ni concurrencia en PostgreSQL.
- El bloque de **contraseñas** y el de **2FA/sesión** están **pendientes de commit local** (slice preparado en el índice); es parte del cierre de la tarea, no de la demostración funcional.

---

## 7. Anexo — Prerrequisitos y comandos para la demo

Antes de capturar pantallas, aplicar las migraciones aditivas en el entorno elegido y conservar `APP_KEY` (los secretos 2FA están cifrados con ella):

```bash
# 1. Dependencias (ya instaladas en la rama: google2fa, bacon-qr-code, symfony/mime parcheado)
composer install --no-interaction

# 2. Aplicar migraciones (no se han ejecutado sobre BD real durante el desarrollo)
php artisan migrate --no-interaction

# 3. Variables de entorno relevantes
#    APP_URL=http://localhost
#    SECURITY_ALERT_RECIPIENT=correo@dominio.com   (para alertas por correo)
#    SESSION_DRIVER=database / SESSION_HTTP_ONLY=true / SESSION_SAME_SITE=lax

# 4. Levantar la aplicación
php artisan serve
# (o) composer run dev  /  npm run dev + php artisan serve
```

**Comandos de verificación (tests)**
```bash
APP_URL=http://localhost php artisan test --compact
node --test tests/Unit/*.test.mjs tests/JavaScript/*.test.js
npm run build
vendor/bin/pint --dirty --format agent
```

**Caveats a mencionar en el informe**
- **Correo real**: en desarrollo usa transporte `log`; la entrega real requiere configurar SMTP y un worker de colas.
- **reCAPTCHA**: aprobado por el docente pero **no implementado** todavía (§4.1).
- **OAuth/JWT**: sustituido por sesiones seguras de Laravel, con aceptación explícita del docente (§5.1).
- **Concurrencia PostgreSQL y navegador real**: no se reclaman como evidencia; la prueba funcional es por tests aislados y capturas manuales.

---

## 8. Bloques pendientes (resumen para completar después)

- 🧩 **§4.1 CAPTCHA/reCAPTCHA**: integrar en login/registro y documentar capturas + pasos.
- 🧩 **§2.5 correo real**: configurar SMTP + worker de colas y capturar el correo al responsable.
- 🧩 **§3.1 expiración (demo manual)**: ajustar `password_changed_at` de una cuenta de prueba para la captura (o documentar solo la evidencia de tests).
- 🧩 **§5.1 OAuth/JWT**: redactar en el informe la justificación de la adaptación a sesiones seguras (ya aceptada por el docente).
