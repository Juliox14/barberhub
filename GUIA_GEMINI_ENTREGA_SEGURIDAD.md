# Instrucciones para Gemini: informe de erradicación de amenazas en BarberHub

Este archivo es una guía de redacción, no el informe terminado ni una certificación de seguridad. Entregalo a Gemini junto con tus capturas, los fragmentos de código y los resultados de pruebas que realmente tengas. Los nombres de imágenes de este archivo son sugeridos: no prueban que esos archivos existan.

## 1. Cómo usar esta guía

1. Tomar las capturas con cuentas y datos de demostración; consultar el manifiesto de la sección 5.
2. Adjuntar a Gemini este archivo, las imágenes disponibles y los resultados de pruebas. Si se adjuntan archivos de código, incluir solamente los necesarios y sin secretos.
3. Completar los datos de portada o dejarlos como campos pendientes.
4. Copiar el siguiente mensaje junto con los adjuntos:

> Usá el archivo GUIA_GEMINI_ENTREGA_SEGURIDAD.md como instrucciones para redactar mi informe académico. Redactá en español formal, desarrollá los ocho requisitos y ubicá cada captura según su identificador. Usá las redacciones base como punto de partida, pero distinguí implementación, resultado comprobado y evidencia pendiente. No inventes capturas, resultados, fechas, personas, correos ni fragmentos de código. Si no recibiste una imagen o un resultado, conservá el marcador correspondiente y señalá que está pendiente. Entregá el informe, no una explicación sobre cómo escribirlo.

## 2. Reglas obligatorias para Gemini

- Escribir un informe técnico académico en español formal, claro y sin afirmaciones comerciales.
- No presentar este archivo de instrucciones como si fuera una evidencia de ejecución.
- No asumir acceso al repositorio ni al contenido de rutas mencionadas: una ruta identifica una fuente, pero no equivale a haber recibido el archivo.
- Diferenciar tres categorías: **mecanismo implementado**, **resultado observado en evidencia adjunta** y **comprobación pendiente**.
- Usar «se verificó», «se observó» o «la prueba aprobó» solamente cuando exista evidencia suministrada que lo respalde. En otro caso, describir el mecanismo y dejar el resultado pendiente.
- No inventar código para hacerlo pasar por código de BarberHub. Si falta el fragmento, insertar `[PENDIENTE: adjuntar fragmento de <ruta>]`.
- No inventar ni reconstruir imágenes. Si falta una captura, mantener su marcador y explicar qué debe mostrar.
- No suponer que los nombres sugeridos del manifiesto son nombres reales de adjuntos. Asociar los adjuntos por contenido; si hay dudas, pedir identificación o mantener el marcador.
- No convertir una captura parcial de terminal en evidencia de controles que no muestra. Describir el alcance exacto del caso ejecutado.
- No afirmar «100 % seguro», «imposible de vulnerar», «cumplimiento total de OWASP», «pentest aprobado» o equivalentes.
- No ocultar errores, pruebas omitidas, evidencia pendiente o límites de recuperación.
- No incluir contraseñas, cookies, tokens CSRF, enlaces de reset completos, secretos 2FA, códigos de recuperación ni información personal real.
- No alterar texto, estado, fecha o resultado de una captura para que coincida con la redacción.
- No confundir mensajes de validación HTML con validación del servidor; transacciones con consultas preparadas; historial con respaldo general; transferencia autorizada con intrusión.
- No afirmar que existen respaldos generales, un visor de correo o detección por geolocalización: no forman parte del alcance documentado.
- Entregar texto de informe listo para pasar a Word o Google Docs. No envolver todo el informe en un bloque de código.

## 3. Contexto técnico y alcance que se puede describir

BarberHub es una aplicación Laravel. La implementación documentada se desarrolló con Laravel 13.34.0 y Breeze 2.4.2.

| Área | Alcance implementado |
| --- | --- |
| SQL | Uso de Eloquent/Query Builder y parámetros separados en consultas explícitas; listas permitidas para ordenación dinámica. |
| CSRF | Protección de rutas web y formularios críticos; pruebas con middleware activo. |
| Entradas | Validación en backend, incluidos tipos de datos, identificadores y motivo de recuperación. |
| Credenciales | Cambio y restablecimiento de contraseña con revocación de accesos anteriores. El cambio exige nuevo inicio de sesión también en el dispositivo que lo solicita. |
| Recuperación | Fecha/hora y estado de citas a partir de su historial, con vista previa, permisos, motivo, validación de agenda y nueva trazabilidad. |
| Bitácora | Eventos de autenticación, contraseñas, sesiones y recuperación, con metadatos limitados. |
| Sesiones | Rechazo de accesos con propiedad de sesión desplazada. No es una detección general de cualquier comportamiento malicioso. |

### Límites que deben figurar en el informe

- La recuperación no reconstruye barbero, servicio o cliente, ni restaura toda la base de datos.
- Solamente pueden recuperarse cambios elegibles cuyo historial corresponda a la cita y coincida con su estado actual; no es un rollback arbitrario de cualquier punto del pasado.
- La recuperación revalida disponibilidad y puede rechazarse si existe un conflicto actual.
- Los cambios de IP o intentos fallidos externos no expulsan por sí solos al usuario legítimo.
- La aplicación aplica sesión única: no presentar dos perfiles como dos sesiones válidas simultáneas de la misma cuenta.
- La revocación central también elimina suscripciones push; puede ser necesario reactivar notificaciones después del reingreso.
- La bitácora no se certificó como inmutable frente a alguien con acceso directo de escritura a la base de datos.
- Las pruebas funcionales no equivalen a un pentest ni a una auditoría exhaustiva de todas las consultas, formularios y entradas.

### Resultados históricos de referencia, no resultados de una ejecución nueva

El seguimiento del proyecto registra estos resultados de la implementación. Si se mencionan sin adjuntar su salida, atribuirlos explícitamente al registro técnico y no a las capturas actuales:

| Comprobación | Resultado registrado |
| --- | --- |
| Verificación independiente de autenticación y entradas | 122 pruebas aprobadas, 822 aserciones. |
| Revalidación de recuperación | 8 pruebas aprobadas, 72 aserciones. |
| Suite completa final de esa implementación | 639 casos: 632 aprobados, 6 fallidos y 1 omitido; 5.608 aserciones; salida de proceso 1. |
| Fallos conocidos | Cuatro casos de AccountSettingsMenuTest y dos de ErrorPageRenderingTest; sus identidades coinciden con la línea base previa. |
| Prueba omitida | GuestBookingConcurrencyTest::test_concurrent_guest_confirmations_wait_and_share_one_customer requiere PostgreSQL y esquemas de prueba; se omite con SQLite. |
| Herramientas | Build del frontend, Pint y comprobación de diferencias aprobaron en esa implementación. |

No afirmar que toda la suite está verde. Si se reciben resultados posteriores, fecharlos, usarlos para esa ejecución y no mezclarlos con estos conteos.

Referencias de implementación: commits locales `bfe0540` y `3181927`; seguimiento `1905973`. Esas referencias no demuestran publicación ni despliegue.

## 4. Estructura del informe que Gemini debe entregar

1. **Portada:** institución, asignatura, docente, estudiante, grupo, fecha y proyecto. Los datos no proporcionados quedan como `[COMPLETAR]`.
2. **Introducción:** propósito de la entrega y relación entre prevención, erradicación y recuperación.
3. **Objetivo general y alcance:** controles implementados, datos recuperables y ambiente de demostración.
4. **Metodología de evidencia:** cuentas demo, perfiles independientes, capturas, código y pruebas aisladas.
5. **Desarrollo:** ocho subsecciones, una por requisito, en el orden de la sección 6.
6. **Matriz de trazabilidad:** requisito, mecanismo, figuras, fuente técnica, resultado y límite.
7. **Resultados y limitaciones:** separar controles verificados, evidencia pendiente, fallos previos y pruebas omitidas.
8. **Conclusiones:** qué puede sostenerse con las evidencias recibidas, sin afirmar certificación general.
9. **Anexos:** comandos ejecutados, salidas completas disponibles y referencias de archivos/commits.

En cada requisito usar: **requisito solicitado → aplicación en BarberHub → procedimiento → evidencias → resultado observado → alcance y limitaciones**.

### Introducción base

> El presente informe documenta los mecanismos implementados en BarberHub para prevenir vulnerabilidades y apoyar la erradicación de accesos comprometidos y la recuperación de información alterada. La exposición relaciona cada requisito con su aplicación en el sistema y con evidencia de interfaz, fragmentos de código o pruebas funcionales. Se distinguen los resultados efectivamente observados de las comprobaciones pendientes y de las limitaciones del alcance.

### Objetivo general base

> Documentar mediante evidencia verificable los controles de consultas parametrizadas, protección CSRF, validación de entradas, revocación de credenciales y sesiones, recuperación de citas y registro de acciones de seguridad implementados en BarberHub.

## 5. Manifiesto de capturas y figuras

Usar el identificador `E##` como referencia estable en el texto y en la leyenda, aunque cambie el nombre real del archivo. Se pueden combinar imágenes relacionadas si se conserva legibilidad y se identifican ambas evidencias. No duplicar imágenes al reutilizar una evidencia: hacer referencia a su identificador.

| ID | Nombre sugerido | Contenido que debe verse | Ubicación en el informe |
| --- | --- | --- | --- |
| E01 | 01-sql-codigo.png | Consulta con `?` y arreglo de bindings; opcionalmente lista permitida de ordenación. | 6.1, después de explicar el mecanismo. |
| E02 | 02-sql-prueba.png | Comando y prueba de payload de ordenación aprobada. | 6.1, después del procedimiento. |
| E03 | 03-csrf-codigo.png | Formulario de recuperación con `@csrf`. | 6.2, después de explicar el token. |
| E04 | 04-csrf-prueba.png | Casos con CSRF activo: token ausente/inválido rechazado y válido aceptado. | 6.2, antes del resultado. |
| E05 | 05-validacion-codigo.png | Reglas del servidor para recuperación o tipo de contraseña actual. | 6.3, después del mecanismo. |
| E06 | 06-validacion-pruebas.png | Rechazo de datos malformados y comprobación de no modificación. | 6.3, antes del resultado. |
| E07 | 07-cambio-clave-cierre.png | Retorno al login después de cambiar la contraseña; sin credenciales visibles. | 6.4, al terminar el procedimiento de cambio. |
| E08 | 08-clave-anterior-rechazada.png | Un intento con contraseña anterior rechazado. | 6.4, después de E07. |
| E09 | 09-reingreso-clave-nueva.png | Acceso exitoso después de ingresar con la nueva contraseña y 2FA si aplica. | 6.4, después de E08. |
| E10 | 10-credenciales-pruebas.png | Pruebas de cambio, reset y rechazo de navegador/cookie anteriores. | 6.4, antes del resultado. |
| E11 | 11-cita-original.png | Cita demo con fecha, hora y estado originales. | 6.5, al describir el estado inicial. |
| E12 | 12-cita-modificada.png | Misma cita después de una modificación controlada. | 6.5, después de E11. |
| E13 | 13-recuperacion-previa.png | Vista previa, valor histórico/actual y motivo de recuperación. | 6.5, antes de la confirmación. |
| E14 | 14-cita-recuperada.png | Cita después de confirmar, con el valor recuperado. | 6.5, después de la confirmación. |
| E15 | 15-recuperacion-pruebas.png | Pruebas de recuperación, permisos, estado vigente, conflicto, CSRF y trazabilidad. | 6.5, después del flujo visual; referenciar también en 6.6. |
| E16 | 16-resumen-pruebas.png | Comando y resultado del conjunto funcional seleccionado; identificar su alcance. | 6.6, antes del análisis de resultados. |
| E17 | 17-bitacora-demo.png | Eventos demo con actor, fecha y barbería; motivo o detalle de recuperación si está disponible. | 6.7, después de describir las acciones registradas. |
| E18 | 18-bitacora-codigo.png | Logger y lista permitida de metadatos; sin información sensible. | 6.7, después del mecanismo de registro. |
| E19 | 19-conflicto-sesion.png | Diálogo «Ya hay una sesión abierta». | 6.8, al iniciar la simulación. |
| E20 | 20-acceso-anterior-cerrado.png | Perfil anterior redirigido a login o aviso de cierre al acceder a pantalla protegida. | 6.8, después de confirmar la transferencia. |
| E21 | 21-sesion-vigente.png | Perfil que recibió la sesión puede continuar. | 6.8, después de E20. |
| E22 | 22-sesiones-prueba.png | Prueba del acceso desplazado y su evento de revocación. | 6.8, antes del resultado. |
| E23 (opcional) | 23-reset-clave-demo.png | Flujo de restablecimiento con correo demo disponible; ocultar token/enlace sensible. | 6.4, en el párrafo de restablecimiento. |

Formato obligatorio para cada imagen disponible:

`[INSERTAR FIGURA E## AQUÍ — archivo real: <nombre recibido>; contenido: <descripción>]`

Debajo colocar:

`Figura E##. <Leyenda específica de lo que realmente muestra>. Fuente: evidencia de la aplicación o de la ejecución de pruebas de BarberHub, proporcionada por el autor. Fecha: <fecha proporcionada o [COMPLETAR]>.`

Si falta:

`[EVIDENCIA E## PENDIENTE — se requiere una captura que muestre <contenido esperado>. No se incluye como resultado verificado.]`

## 6. Redacciones y colocación por requisito

Las siguientes son redacciones base del mecanismo. Gemini debe agregar resultados empíricos únicamente a partir de los adjuntos.

### 6.1 Uso de consultas preparadas

**Requisito:** «Todas las consultas a la base de datos deberán implementarse mediante consultas preparadas para evitar inyecciones SQL. Se deberá evidenciar el uso de este mecanismo en el código del sistema.»

**Redacción base:**

> BarberHub utiliza Eloquent y Query Builder para el acceso a datos. En las consultas explícitas con valores variables se separa la instrucción SQL de los parámetros mediante placeholders y bindings. La ordenación dinámica utiliza listas permitidas de columnas y direcciones, ya que los bindings de valores no sustituyen la validación de identificadores SQL.

**Fuentes:** `app/Support/GuestCustomerResolver.php` (por ejemplo, `whereRaw('LOWER(TRIM(email)) = ?', [$email])`), `app/Http/Controllers/TenantReportController.php` y `app/Support/TableSort.php`.

**Ubicación:** insertar E01 tras el párrafo técnico; luego describir el caso de entrada de ordenación malformada e insertar E02.

**Resultado a completar:** indicar qué entrada se probó, qué respuesta se obtuvo y cómo se comprobó que los registros permanecieron intactos. No convertir una prueba de ordenación en una afirmación de revisión exhaustiva de todas las consultas.

### 6.2 Implementación de tokens CSRF

**Requisito:** «Los formularios y procesos críticos deberán incluir tokens de seguridad que validen la autenticidad de las solicitudes, evitando envíos maliciosos desde sitios externos.»

**Redacción base:**

> Los formularios web críticos incorporan un token CSRF que el servidor valida antes de permitir la operación. El formulario de recuperación de citas incluye la directiva `@csrf`. Las pruebas del control habilitan efectivamente el middleware y contrastan solicitudes sin token, con token inválido y con token válido.

**Fuentes:** `resources/views/tenant/appointments-restore.blade.php` y `tests/Feature/TenantAppointmentRestorationTest.php`.

**Ubicación:** E03 después de explicar el formulario; E04 después del procedimiento de prueba.

**Resultado a completar:** registrar los rechazos 419 y la ausencia de modificaciones solo si se muestran en los casos o la salida adjunta. Aclarar que la prueba controla el formulario ensayado, no todos los formularios del sistema.

### 6.3 Validación de entradas en backend

**Requisito:** «Toda la información ingresada por el usuario deberá validarse en el servidor, asegurando que los datos sean correctos y evitando la ejecución de código malicioso o el ingreso de datos inválidos.»

**Redacción base:**

> La aplicación valida las entradas en el servidor antes de ejecutar cambios. En la recuperación se validan el identificador del evento, la fecha y el estado esperados y un motivo obligatorio no vacío con un máximo de 500 caracteres. En el cambio de contraseña se valida el tipo de la contraseña actual antes de enviarla al servicio de rotación. La validación se complementa con autorización, parametrización y escape de salida; no los reemplaza.

**Fuentes:** `app/Http/Requests/AppointmentRestorationRequest.php`, `app/Http/Controllers/Auth/PasswordController.php` y `tests/Feature/ThreatEradicationInputTest.php`.

**Ubicación:** E05 tras las reglas; E06 tras explicar los datos malformados.

**Resultado a completar:** distinguir la validación del servidor de los mensajes HTML del navegador. Describir las aserciones de conservación de credenciales/sesión/bitácora o cita/historial que correspondan al caso recibido.

### 6.4 Restablecimiento de credenciales comprometidas

**Requisito:** «El sistema deberá permitir el cambio de contraseña en caso de incidente, así como el cierre automático de sesiones activas, garantizando que accesos previos no autorizados sean eliminados.»

**Redacción base:**

> El cambio y el restablecimiento de contraseña utilizan una rotación transaccional de credenciales y la revocación central de sesiones. La revocación elimina las sesiones persistidas, rota el token de recuerdo, incrementa la versión de seguridad y elimina la propiedad anterior de sesión. El cambio desde el perfil también cierra el acceso del dispositivo iniciador y requiere autenticación con la nueva contraseña.

**Procedimiento a redactar:** cambiar contraseña desde Perfil; observar retorno a login; intentar una sola vez la contraseña anterior; ingresar con la nueva y cumplir 2FA si corresponde. Para el restablecimiento por correo, describir el flujo visual únicamente si se proporcionó evidencia de una bandeja demo ya disponible; de lo contrario, utilizar la prueba de reset y declarar pendiente la captura visual.

**Fuentes:** `app/Support/PasswordRotation.php`, `app/Support/SingleDeviceSession.php`, `app/Http/Controllers/Auth/PasswordController.php`, `app/Http/Controllers/Auth/NewPasswordController.php` y pruebas `Auth/PasswordUpdateTest.php`, `Auth/PasswordResetTest.php`, `Auth/SingleDeviceSessionTest.php`.

**Ubicación:** E07, E08 y E09 en orden cronológico; E23 solo si está disponible; E10 después de describir la revocación técnica.

**Cuidado:** no afirmar que ambos perfiles mantenían sesiones válidas simultáneas. No afirmar recepción de correo con mailers `array`/`null` ni inventar un visor de mensajes.

### 6.5 Corrección de registros alterados mediante historial

**Requisito:** «Se deberá contar con un mecanismo que permita recuperar información que haya sido modificada de forma indebida. Esto puede realizarse mediante un historial de cambios o respaldos de la base de datos, permitiendo restaurar valores originales.»

**Redacción base:**

> La recuperación de citas se apoya en el historial de cambios de fecha/hora y estado. El usuario autorizado selecciona un evento elegible y revisa el valor anterior y el actual antes de confirmar con un motivo. El servidor verifica la pertenencia del evento, los permisos, la vigencia del estado y las relaciones de la cita. Si el destino vuelve a estar programado, se aplican las validaciones de disponibilidad y conflictos. La operación se ejecuta en una transacción y agrega eventos de historial y bitácora sin eliminar los anteriores.

**Procedimiento a redactar:** cita demo original → reprogramación controlada → «Historial recuperable» → selección del cambio → «Revisar recuperación de cita» → motivo → «Confirmar recuperación» → cita recuperada. Para citas cerradas existe «Historial de citas finalizadas» con enlaces de recuperación.

**Fuentes:** `app/Support/AppointmentRestoration.php`, `app/Http/Requests/AppointmentRestorationRequest.php`, `app/Http/Controllers/TenantAppointmentController.php`, `resources/views/tenant/appointments-index.blade.php`, `resources/views/tenant/appointments-restore.blade.php` y `tests/Feature/TenantAppointmentRestorationTest.php`.

**Ubicación:** E11 al presentar el estado inicial; E12 después de la alteración simulada; E13 antes de confirmar; E14 al describir el resultado; E15 al explicar controles negativos.

**Límite obligatorio:** recuperar solamente fecha/hora y estado históricos elegibles. No describirlo como un respaldo completo, una recuperación de todos los campos o una restauración sin validaciones. Llamar a la modificación «simulación controlada», no incidente real demostrado.

### 6.6 Validación de eliminación de la amenaza

**Requisito:** «Después de aplicar las correcciones, se deberá comprobar que la vulnerabilidad ya no puede ser explotada, mediante pruebas dentro del sistema.»

**Redacción base:**

> La validación funcional contrasta solicitudes admitidas y rechazadas y comprueba sus efectos observables. Los casos cubren entradas de ordenación no permitidas, solicitudes sin CSRF válido, datos malformados, accesos anteriores a una rotación de credenciales y operaciones de recuperación incompatibles con las reglas actuales. Estas comprobaciones aportan evidencia de los escenarios ensayados, sin constituir una evaluación exhaustiva de todas las posibles vulnerabilidades.

**Ubicación:** insertar E16 después de identificar comando, ambiente y alcance. Referenciar E02, E04, E06, E10, E15 y E22 sin duplicar imágenes.

**Resultado a completar:** nombre del conjunto, número de casos aprobados/fallidos/omitidos y fecha de esa ejecución. No confundir el conjunto seleccionado con la suite completa. Si se usa el registro histórico, citarlo como tal y conservar sus seis fallos y una omisión.

### 6.7 Registro de acciones o bitácora

**Requisito:** «El sistema deberá almacenar un registro de los eventos de seguridad, como intentos de acceso fallidos, cambios de contraseña o acciones realizadas durante la erradicación del incidente.»

**Redacción base:**

> BarberHub registra eventos de autenticación, cambios y restablecimientos de contraseña, transferencias o revocaciones de sesión y recuperación de citas. El mecanismo utiliza una lista permitida de metadatos y límites de tamaño. La interfaz de consulta se encuentra en «Bitácora y alertas» y requiere una cuenta de administrador de plataforma.

**Procedimiento a redactar:** realizar acciones demo, entrar como administrador de plataforma, abrir la bitácora e identificar eventos por usuario, fecha y barbería. Describir solamente los eventos que realmente figuren en las capturas. La recuperación genera `appointment.restored` con referencia a cita, evento histórico y motivo.

**Fuentes:** `app/Support/SecurityAuditLogger.php`, `app/Http/Controllers/SecurityAuditLogController.php` y `tests/Feature/SecurityAuditLogTest.php`.

**Ubicación:** E17 al describir los registros observados; E18 después de explicar la limitación de metadatos.

**Límite:** no afirmar inmutabilidad certificada, disponibilidad infalible del registro ni ausencia absoluta de datos sensibles basándose solamente en una captura.

### 6.8 Invalidación de sesiones sospechosas

**Requisito:** «El sistema deberá detectar comportamientos inusuales y cerrar automáticamente sesiones activas cuando se identifique actividad sospechosa, protegiendo el acceso a la información.»

**Redacción base:**

> La regla demostrada identifica solicitudes que presentan una propiedad de sesión desplazada respecto de la vigente. La aplicación invalida el acceso afectado y, en el recorrido del middleware correspondiente, registra una revocación con motivo `displaced_owner_token`. El control no interpreta un cambio de IP o un intento fallido externo como razón suficiente para expulsar al usuario legítimo.

**Procedimiento a redactar:** sesión en perfil A → login de la misma cuenta en B → 2FA si corresponde → diálogo «Ya hay una sesión abierta» → «Cambiar a este dispositivo» → acceso protegido desde A rechazado → B continúa operativo.

**Fuentes:** `resources/views/components/session-conflict-modal.blade.php`, `app/Http/Middleware/EnsureSingleDeviceSession.php` y `tests/Feature/Auth/SingleDeviceSessionTest.php`.

**Ubicación:** E19 antes de transferir, E20 después del rechazo del perfil anterior, E21 para la continuidad de la sesión vigente y E22 para la prueba determinista de revocación y auditoría.

**Matiz obligatorio:** la transferencia autorizada es una simulación del desplazamiento, no una intrusión demostrada. El sondeo de estado del navegador puede cerrar el acceso por un recorrido diferente al middleware; no asegurar que cada cierre visual producirá exactamente el mismo evento. Utilizar la prueba específica para demostrar ese recorrido, sin manipular cookies para fabricar una captura.

## 7. Comandos para obtener evidencia real

Estos comandos son para el autor o un entorno autorizado. Gemini no debe afirmar que los ejecutó si no dispone de herramientas y resultados reales. Ejecutar desde la raíz del proyecto con dependencias ya instaladas; no ejecutar migraciones ni seeders para esta guía.

Preparación de Bash:

```bash
test_evidencia() {
  APP_ENV=testing \
  DB_CONNECTION=sqlite \
  DB_DATABASE=:memory: \
  DB_URL='' \
  CACHE_STORE=array \
  SESSION_DRIVER=array \
  MAIL_MAILER=array \
  QUEUE_CONNECTION=sync \
  RECAPTCHA_ENABLED=false \
  vendor/bin/phpunit --testdox "$@"
}
```

Consultas y ordenación (E02):

```bash
test_evidencia tests/Feature/ThreatEradicationInputTest.php \
  --filter test_raw_ordering_payloads_fall_back_to_allowlisted_sort_and_direction
```

CSRF con protección activa (E04):

```bash
test_evidencia tests/Feature/TenantAppointmentRestorationTest.php \
  --filter test_restore_requires_csrf_token_and_rejects_missing_or_invalid_tokens_without_mutation
```

Validación backend (E06):

```bash
test_evidencia tests/Feature/ThreatEradicationInputTest.php \
  --filter 'test_array_current_password|test_malformed_appointment_input'
```

Cambio y restablecimiento de contraseña (E10):

```bash
test_evidencia tests/Feature/Auth/PasswordUpdateTest.php \
  tests/Feature/Auth/PasswordResetTest.php

test_evidencia tests/Feature/Auth/SingleDeviceSessionTest.php \
  --filter test_password_change_invalidates_old_browser_and_remember_cookie
```

Recuperación (E15):

```bash
test_evidencia tests/Feature/TenantAppointmentRestorationTest.php
```

Conjunto funcional seleccionado (E16):

```bash
test_evidencia \
  tests/Feature/ThreatEradicationInputTest.php \
  tests/Feature/Auth/PasswordUpdateTest.php \
  tests/Feature/Auth/PasswordResetTest.php \
  tests/Feature/Auth/SingleDeviceSessionTest.php \
  tests/Feature/TenantAppointmentRestorationTest.php \
  tests/Feature/SecurityAuditLogTest.php
```

Sesión desplazada y registro (E22):

```bash
test_evidencia tests/Feature/Auth/SingleDeviceSessionTest.php \
  --filter test_transfer_requires_confirmation_then_rejects_old_browser_on_protected_request
```

La captura debe mostrar el comando, los casos y el resumen, con texto legible. No recortar errores para hacer parecer aprobada una ejecución fallida. Los conteos de distintas ejecuciones no se suman, porque pueden repetir casos.

## 8. Matriz final que Gemini debe completar

| Requisito | Evidencias previstas | Resultado observado | Pendientes o límites |
| --- | --- | --- | --- |
| Consultas preparadas | E01–E02 | Completar desde adjuntos. | No inferir revisión exhaustiva de toda la app. |
| CSRF | E03–E04 | Completar desde adjuntos. | Protección activa en los casos probados. |
| Validación backend | E05–E06 | Completar desde adjuntos. | No confundir validación del navegador con servidor. |
| Credenciales comprometidas | E07–E10; E23 opcional | Completar desde adjuntos. | Reset visual condicionado a correo demo disponible. |
| Recuperación de registros | E11–E15 | Completar desde adjuntos. | Fecha/hora y estado de citas, no respaldo general. |
| Validación de la corrección | E16 y referencias a otras pruebas | Completar desde adjuntos. | No equivalencia a pentest; declarar errores/omisiones. |
| Bitácora | E17–E18 | Completar desde adjuntos. | Sin certificación de inmutabilidad. |
| Sesiones sospechosas | E19–E22 | Completar desde adjuntos. | Regla de sesión desplazada, no detección general. |

## 9. Cierre y revisión antes de entregar

La conclusión debe indicar qué mecanismos fueron documentados y cuáles se comprobaron efectivamente en los adjuntos. Si faltan imágenes o resultados, señalarlo sin declarar incumplimiento definitivo ni cumplimiento completo por anticipado.

Checklist para el autor y Gemini:

- [ ] Portada sin datos inventados; campos faltantes marcados.
- [ ] Ocho requisitos tratados y vinculados a evidencias reales.
- [ ] Cada figura insertada donde corresponde, con ID, leyenda y fecha verificable o pendiente.
- [ ] Figuras faltantes conservadas como pendientes, no reemplazadas por imágenes ficticias.
- [ ] Estado inicial, modificación y recuperación corresponden a la misma cita demo.
- [ ] Hora/fecha de la vista previa y del resultado coinciden en el caso presentado.
- [ ] Cambio de contraseña distingue cierre, rechazo de la anterior y acceso con la nueva.
- [ ] Capturas de terminal corresponden al comando y al conjunto declarado.
- [ ] Resultados históricos separados de ejecuciones actuales; no sumar casos repetidos.
- [ ] Fallos y pruebas omitidas declarados; no afirmar que toda la suite está verde.
- [ ] Datos sensibles, enlaces de reset y secretos 2FA ocultos.
- [ ] Sin afirmaciones de pentest, respaldo general o detección universal.
- [ ] Límites de recuperación y de sesión única explicados.
- [ ] El informe final no incluye estas instrucciones operativas como si fueran resultados del proyecto.
