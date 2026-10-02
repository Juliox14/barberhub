# BarberHub — Contexto Maestro del Proyecto

> Este documento contiene las reglas, decisiones y contexto fundamental de BarberHub.
> Debe considerarse una fuente de verdad del proyecto.
> Los agentes deben leer este documento antes de realizar cambios importantes.
> Las decisiones aquí establecidas no deben modificarse silenciosamente.

---

## 1. Identidad del proyecto

**Nombre:** BarberHub

**Tipo:** Plataforma web SaaS multi-tenant para agendas, administración de barberías y experiencia digital de sus clientes.

**Propósito académico:** Proyecto para la materia Desarrollo de Aplicaciones Web y Móviles.

El proyecto debe demostrar buenas prácticas de desarrollo, arquitectura MVC y el uso justificado del patrón Singleton, sin introducir complejidad innecesaria únicamente para cumplir requisitos académicos.

**Visión del producto:** BarberHub debe poder ofrecerse a distintas barberías como un servicio. Cada negocio contará con un espacio independiente para administrar sus operaciones y ofrecer a sus clientes una experiencia de reserva y recomendaciones de cortes.

---

## 2. Objetivo general

Crear una plataforma que permita a múltiples barberías administrar sus operaciones principales desde un mismo sistema, manteniendo aislados los datos y la configuración de cada negocio.

La plataforma incluirá:
- Administración de barberías, personal, servicios, horarios y clientes.
- Agenda y gestión de citas.
- Historial de servicios y cortes.
- Experiencia de reserva para clientes.
- Recomendaciones personalizadas de cortes considerando información del cabello, preferencias e identificación aproximada de características faciales mediante Computer Vision.

El sistema debe complementar el criterio profesional del barbero, no sustituirlo.

BarberHub tendrá dos ámbitos diferenciados:
1. **Administración de la plataforma:** gestión de barberías registradas y, en una fase comercial, planes y suscripciones.
2. **Operación de cada barbería:** administración cotidiana del negocio y experiencia de sus clientes.

---

## 3. Modelo SaaS y multi-tenancy

BarberHub será una aplicación multi-tenant: una misma plataforma atenderá a varias barberías, cada una con datos y configuración independientes.

### 3.1 Aislamiento

Como arquitectura inicial se contempla una aplicación Laravel y una base de datos PostgreSQL compartida, con aislamiento lógico por barbería. La estrategia definitiva debe validarse antes de implementar el esquema.

Las entidades que pertenezcan a un negocio deberán estar asociadas a su barbería, normalmente mediante `barbershop_id`.

Reglas obligatorias:
- Un usuario de una barbería no debe poder acceder a registros de otra barbería salvo que tenga un permiso explícito de plataforma.
- No confiar únicamente en filtros de interfaz: el aislamiento debe verificarse en el servidor.
- Validar que las relaciones entre registros pertenezcan al mismo tenant. Por ejemplo, una cita no puede asociar un barbero de una barbería con un servicio de otra.
- Revisar cuidadosamente consultas, rutas, políticas, trabajos en segundo plano, exportaciones y archivos para evitar fugas entre tenants.
- Probar explícitamente intentos de acceso cruzado entre barberías.
- No confiar en un `barbershop_id` enviado por el navegador sin verificar que el usuario tenga acceso a esa barbería.

No implementar múltiples bases de datos por barbería ni infraestructura compleja sin una necesidad demostrada y una decisión aprobada.

### 3.2 Identidad y pertenencia de usuarios

La identidad de una persona debe distinguirse de su pertenencia a una barbería.

Se contempla que una cuenta pueda pertenecer a más de una barbería y tener un rol diferente en cada una. Por ello, no asumir que una sola columna global `role` en `users` será suficiente.

Modelo conceptual inicial:
- `users`: identidad y datos generales de autenticación.
- `barbershops`: negocios registrados en BarberHub.
- `memberships` o entidad equivalente: relación entre usuario y barbería, con rol y estado de membresía.

La estructura exacta debe diseñarse y aprobarse antes de crear migraciones.

Los clientes pueden utilizar una identidad global, pero su historial, preferencias y datos visibles para cada negocio deben tener límites claros. No compartir información entre barberías automáticamente. Definir qué datos son globales, cuáles son propios de cada tenant y qué requiere consentimiento del cliente.

### 3.3 Identificadores públicos

Se puede ofrecer una página pública por barbería, por ejemplo mediante un slug único (`/nombre-barberia`). La estrategia de URLs, dominios personalizados y marca configurable queda pendiente de definición; no implementarla prematuramente.

---

## 4. Stack tecnológico

### Backend
- PHP
- Laravel

### Base de datos
- PostgreSQL

### Frontend
- Blade
- Tailwind CSS
- JavaScript

### Control de versiones
- Git
- GitHub

### Futuro
- PWA
- Computer Vision
- Posiblemente Python para componentes específicos de análisis facial
- Suscripciones y pagos, cuando exista una decisión comercial y técnica

No asumir versiones instaladas. Verificar el entorno y seleccionar versiones compatibles al iniciar el proyecto.

---

## 5. Arquitectura

La arquitectura principal debe seguir el patrón:

**MVC — Model View Controller**

Las responsabilidades deben mantenerse separadas.

### Model
Responsable de:
- Representar entidades y datos.
- Definir relaciones entre entidades.
- Interactuar con la base de datos mediante las herramientas adecuadas de Laravel.
- Mantener invariantes de dominio cuando corresponda.

### View
Responsable de:
- Presentación e interfaz de usuario.
- Formularios.
- Visualización de información.
- No decidir permisos ni ser responsable de reglas críticas del negocio.

### Controller
Responsable de:
- Recibir solicitudes.
- Validar y coordinar operaciones.
- Utilizar modelos, servicios y políticas.
- Devolver respuestas o vistas.

Evitar colocar lógica de negocio compleja directamente en las vistas o grandes cantidades de lógica en los controladores. Cuando una lógica sea suficientemente compleja, evaluar servicios u otras capas apropiadas.

### Contexto del tenant

El sistema debe determinar de forma segura el contexto de barbería para cada solicitud. La estrategia (por ejemplo, middleware y resolución de membresía) debe definirse de forma consistente.

No duplicar validaciones de tenant de manera improvisada en cada vista. Centralizar los mecanismos de autorización y filtrado cuando sea apropiado, sin ocultar cómo funciona el aislamiento.

---

## 6. Patrón Singleton

BarberHub debe utilizar el patrón Singleton porque forma parte de los requisitos académicos.

Sin embargo, no debe utilizarse únicamente para demostrar que se utilizó.

Antes de implementar un Singleton, el agente debe:
1. Identificar dónde tendría sentido utilizarlo.
2. Explicar qué problema resuelve.
3. Evaluar si realmente es necesario.
4. Evitar utilizar Singleton en clases donde la inyección de dependencias de Laravel sea una solución más apropiada.

La implementación debe mantenerse sencilla y comprensible. No utilizar Singleton para representar usuarios, barberías, sesiones o contexto de tenant sin una justificación técnica y revisión explícita.

---

## 7. Usuarios, roles y permisos

Los roles deben interpretarse dentro de su ámbito. Se contemplan roles de plataforma y roles asociados a una barbería.

### 7.1 Superadministrador de BarberHub

Ámbito: plataforma completa.

Responsabilidades potenciales:
- Registrar, revisar, activar, suspender o administrar barberías.
- Consultar información operativa necesaria para soporte, respetando privacidad y minimización.
- Administrar planes y suscripciones cuando se implemente la parte comercial.
- Gestionar configuraciones globales de la plataforma.

El superadministrador no debe confundirse con el administrador de una barbería. Las capacidades exactas de soporte y acceso a datos deben definirse explícitamente.

### 7.2 Administrador de barbería

Ámbito: una barbería específica.

Responsabilidades:
- Administrar la configuración de su negocio.
- Gestionar miembros y barberos de su barbería.
- Administrar servicios, precios, duración y disponibilidad.
- Consultar y gestionar la agenda.
- Consultar información y reportes de su negocio.
- Administrar la información de clientes dentro de los límites de privacidad y permisos.

No puede administrar otras barberías ni funciones exclusivas de la plataforma.

### 7.3 Barbero

Ámbito: barbería o barberías a las que pertenece.

Responsabilidades:
- Consultar su agenda.
- Consultar la información necesaria de sus citas y clientes.
- Registrar servicios realizados.
- Registrar características del cabello y observaciones pertinentes.
- Consultar recomendaciones autorizadas.
- Gestionar su disponibilidad si el administrador lo permite.

No puede modificar la configuración global ni acceder a información de otros negocios.

### 7.4 Cliente

Ámbito: su cuenta y las relaciones que mantiene con cada barbería.

Responsabilidades:
- Administrar sus datos y preferencias.
- Consultar y gestionar sus citas conforme a las reglas del negocio.
- Consultar el historial disponible de cada barbería.
- Proporcionar información sobre su cabello y preferencias.
- Consultar recomendaciones y corregir información estimada cuando sea posible.

Un cliente no puede acceder a información privada de otros clientes.

### 7.5 Autorización

- La autenticación identifica al usuario; la autorización determina sus acciones permitidas.
- Implementar autorización del lado del servidor mediante mecanismos apropiados de Laravel (middleware, policies, gates u otros).
- No permitir que el registro público asigne el rol de superadministrador o administrador de barbería por elección libre del usuario.
- Las invitaciones, altas y cambios de rol deben comprobar quién tiene autoridad para realizarlos.
- Probar permisos para cada rol y para usuarios con membresías en más de una barbería.

Los roles y permisos concretos deben documentarse y mantenerse consistentes en rutas, controladores y pruebas.

---

## 8. Módulos principales

El desarrollo será progresivo. Los módulos previstos son:

### 8.1 Plataforma y barberías
- Registro y administración de barberías.
- Estado de la barbería (por ejemplo, activa o suspendida).
- Datos públicos y configuración del negocio.
- Membresías de usuarios.
- Gestión global desde el ámbito de plataforma.

### 8.2 Usuarios y autenticación
- Autenticación.
- Recuperación de contraseña.
- Perfil general.
- Membresías y roles por barbería.
- Autorización.

Se puede utilizar Laravel Breeze u otra solución compatible para acelerar la autenticación, pero roles, membresías y permisos son responsabilidad del diseño de BarberHub.

### 8.3 Clientes
- Perfil e información personal necesaria.
- Preferencias.
- Información del cabello.
- Citas.
- Historial por barbería.
- Consentimientos y opciones de privacidad cuando apliquen.

### 8.4 Barberos y personal
- Perfil profesional.
- Membresía a una o varias barberías, si se habilita.
- Disponibilidad.
- Agenda.
- Historial de servicios realizados.
- Estado dentro de cada negocio.

### 8.5 Servicios
Cada servicio pertenece a una barbería y puede contener:
- Nombre.
- Descripción.
- Precio.
- Duración estimada.
- Estado.
- Reglas de disponibilidad, si son necesarias.

### 8.6 Horarios y disponibilidad
- Horarios de atención de la barbería.
- Horarios individuales de barberos.
- Descansos, bloqueos y excepciones.
- Zona horaria del negocio.
- Reglas para calcular espacios disponibles.

Debe definirse cómo se combinan horarios generales, horarios individuales y excepciones antes de implementar el calendario.

### 8.7 Citas y agenda
Debe permitir:
- Crear citas.
- Consultar disponibilidad.
- Asociar cliente, barbero, servicio y barbería.
- Definir fecha, hora, duración y estado.
- Cancelar y reprogramar conforme a reglas.
- Registrar notas pertinentes.
- Evitar solapamientos y reservas simultáneas incompatibles.

Las reglas críticas deben validarse en el servidor y, cuando corresponda, protegerse con transacciones o restricciones de base de datos.

### 8.8 Historial de cortes y servicios
Puede registrar:
- Fecha.
- Barbería.
- Barbero.
- Servicio y tipo de corte.
- Características del cabello relevantes.
- Longitud y estilo.
- Observaciones.
- Preferencias o comentarios del cliente.

El historial debe tener alcance y permisos definidos por barbería. No compartirlo entre negocios automáticamente.

### 8.9 Experiencia pública y reservas
- Página pública de cada barbería.
- Presentación de servicios y barberos.
- Selección de servicio, barbero y horario.
- Flujo de reserva.
- Consulta de citas por parte del cliente.

La personalización de marca, dominios propios y páginas avanzadas quedan para una fase posterior.

### 8.10 Planes y suscripciones
Módulo comercial futuro:
- Planes disponibles.
- Límites y funcionalidades por plan.
- Estado de suscripción.
- Pagos y facturación si se decide incorporarlos.
- Reglas de suspensión o cambio de plan.

No integrar pagos ni imponer un modelo de precios hasta que se defina el alcance comercial.

---

## 9. Sistema de recomendaciones

Esta es una de las características diferenciadoras de BarberHub.

El sistema deberá recomendar estilos de corte considerando múltiples factores:
- Forma facial estimada.
- Tipo, textura, grosor, densidad y longitud del cabello.
- Preferencias de estilo.
- Facilidad de peinado y mantenimiento deseado.
- Historial de cortes, si el cliente decide utilizarlo.
- Criterio profesional y disponibilidad de servicios del negocio, cuando sea pertinente.

Las recomendaciones son orientación, no una regla estética universal. Deben ser explicables y permitir que el cliente y el barbero ejerzan su criterio.

### 9.1 Factores faciales

Se podrá utilizar Computer Vision para estimar aproximadamente la forma del rostro.

Categorías iniciales posibles:
- Ovalado.
- Redondo.
- Cuadrado.
- Rectangular.
- Corazón.
- Diamante.

La clasificación será aproximada y dependiente de la calidad de la imagen y del método utilizado. No debe presentarse como medición médica ni como verdad absoluta.

Utilizar lenguaje como: **“Forma facial estimada: ovalada”**, no afirmaciones absolutas.

### 9.2 Información del cabello

Características posibles:
- Tipo de cabello.
- Textura.
- Grosor.
- Densidad.
- Longitud.
- Facilidad de peinado.
- Tiempo disponible para mantenimiento.
- Preferencias de estilo.
- Estilos que no desea.

Debe distinguirse entre información declarada por el cliente, observaciones del barbero y estimaciones automatizadas.

### 9.3 Motor de recomendaciones

Inicialmente será un sistema basado en reglas.

Las reglas deben poder explicar por qué se recomienda un estilo. No implementar Machine Learning complejo si las reglas son suficientes para el MVP.

Ejemplo conceptual:

**Forma facial estimada:** ovalada
**Cabello:** grueso y ondulado
**Mantenimiento deseado:** bajo

Resultado: “Este estilo podría ser compatible con la forma facial estimada y las características del cabello. Su mantenimiento suele ser bajo; consulta con tu barbero si se adapta a tu tipo de cabello y resultado deseado”.

El recomendador debe ser un módulo desacoplado de agenda y administración. Su disponibilidad puede configurarse por barbería en el futuro, sin que eso complique el MVP.

---

## 10. Computer Vision

Inicialmente se busca utilizar Computer Vision local.

La idea es utilizar landmarks faciales para obtener proporciones aproximadas del rostro. Se podrían analizar:
- Ancho y alto del rostro.
- Proporciones generales.
- Línea de mandíbula.
- Frente.
- Pómulos.

El objetivo es obtener una clasificación aproximada de la forma facial.

### Restricciones iniciales
- No entrenar modelos propios.
- No utilizar Machine Learning innecesario.
- No utilizar IA generativa para recomendar cortes.
- No depender de APIs de IA externas de pago.
- Priorizar procesamiento local cuando sea viable.
- No afirmar precisión que no haya sido evaluada.

La implementación de Computer Vision es una fase posterior al flujo central de la plataforma.

---

## 11. Privacidad y seguridad de datos

Las fotografías faciales y la información derivada de ellas requieren especial cuidado.

Principios:
- Minimizar la información almacenada.
- Procesar localmente cuando sea viable.
- Evitar almacenar fotografías innecesariamente.
- Separar los datos de autenticación de los datos de perfil y de análisis.
- Explicar claramente qué información se utiliza y para qué.
- No implementar almacenamiento permanente de fotografías faciales sin justificación y consentimiento adecuados.
- Permitir revisar y corregir datos de perfil cuando sea posible.
- Restringir el acceso al historial y a la información de clientes según el contexto de barbería y los permisos.
- Evitar que una barbería vea datos de otra por defecto.

El diseño debe contemplar que los clientes puedan utilizar varias barberías sin que eso implique compartir automáticamente sus historiales o fotografías.

Antes de un despliegue comercial, revisar obligaciones legales y de privacidad aplicables a los datos tratados y al país donde opere el servicio.

---

## 12. Diseño e identidad visual

BarberHub debe tener una identidad visual coherente y reconocible, pero la interfaz debe adaptarse a las tareas de cada usuario.

Principios:
- Priorizar claridad, accesibilidad y facilidad de uso.
- Mantener consistencia entre pantallas.
- Distinguir visualmente la experiencia pública del cliente y los paneles operativos.
- Diseñar primero flujos funcionales y después refinarlos.
- Utilizar un sistema de diseño compartido para colores, tipografía, espaciado, componentes y estados.
- No sacrificar legibilidad de calendarios, tablas y formularios por originalidad visual.
- Las opciones de personalización por barbería deben respetar límites y componentes comunes de la plataforma.

Las herramientas o skills de diseño (incluida Hallmark, si se utiliza) son auxiliares. No pueden cambiar decisiones aprobadas del sistema de diseño sin autorización.

---

## 13. PWA

La conversión a PWA será una fase posterior.

La primera versión debe ser una aplicación web funcional y usable en dispositivos móviles.

Desde el diseño inicial se debe evitar tomar decisiones que dificulten posteriormente la conversión a PWA, pero no implementar funcionalidades PWA prematuramente.

No asumir que las operaciones críticas (como reservar o cancelar) estarán disponibles offline sin diseñar explícitamente la sincronización y resolución de conflictos.

---

## 14. Principios de desarrollo

Todos los agentes deben seguir estos principios:

### Simplicidad
Preferir la solución más sencilla que resuelva correctamente el problema.

### Mantenibilidad
El código debe ser comprensible para otro desarrollador.

### Separación de responsabilidades
Cada componente debe tener una responsabilidad clara.

### Evitar sobreingeniería
No crear abstracciones, servicios, patrones o dependencias sin una razón concreta.

### Seguridad
Validar entradas y autorizar operaciones en el servidor. Nunca confiar directamente en datos proporcionados por el cliente.

### Aislamiento multi-tenant
Toda operación sobre datos de negocio debe respetar el tenant activo y comprobar pertenencia y permisos.

### Consistencia
Seguir las convenciones de Laravel siempre que sean adecuadas.

### Cambios pequeños
Preferir cambios pequeños, verificables y fáciles de revertir.

### Experiencia de usuario
La interfaz debe ser clara y eficiente para clientes, barberos y administradores.

---

## 15. Dependencias

No instalar una dependencia nueva sin evaluar primero:
1. Si realmente es necesaria.
2. Si Laravel ya proporciona una solución.
3. Si existe una alternativa más sencilla.
4. Si es compatible con las versiones instaladas.
5. Qué impacto tendrá en seguridad, mantenimiento y complejidad.

Las dependencias deben mantenerse al mínimo razonable. No instalar paquetes multi-tenancy, roles, pagos o UI sin revisar primero si su complejidad y modelo encajan con el alcance real.

---

## 16. Git

Git debe utilizarse durante todo el desarrollo.

Los commits deben ser:
- Pequeños.
- Relacionados con un cambio lógico.
- Descriptivos.

Formato preferido del usuario para commits: fecha + ADD/FIX/DELETE + descripción.

No realizar commits gigantes que mezclen funcionalidades no relacionadas.

Antes de realizar cambios importantes:
- Revisar el estado actual del repositorio.
- No sobrescribir trabajo existente sin analizarlo.
- No hacer commit, push, reset, rebase ni operaciones destructivas sin autorización explícita cuando la tarea no lo solicite.

---

## 17. Reglas para agentes

Antes de modificar código:
1. Leer `BARBERHUB_CONTEXT.md`.
2. Revisar el estado actual del repositorio.
3. Revisar las decisiones relevantes almacenadas en Engram.
4. Comprender el objetivo y el alcance de la tarea.
5. Identificar archivos afectados.
6. Revisar las convenciones y versiones reales del proyecto.
7. Explicar brevemente el plan cuando el cambio sea significativo.

Durante la implementación:
- No modificar archivos no relacionados sin justificación.
- No cambiar decisiones arquitectónicas silenciosamente.
- No agregar funcionalidades no solicitadas.
- No introducir dependencias innecesarias.
- Mantener los cambios enfocados.
- Respetar el aislamiento de datos entre barberías.
- No inventar resultados de pruebas, comandos o verificaciones.
- No ejecutar operaciones destructivas ni publicar cambios sin autorización.
- Si falta una decisión que afecte al modelo de datos, seguridad o alcance, explicarla y preguntar antes de consolidarla.

Después de implementar:
1. Ejecutar pruebas relevantes.
2. Revisar errores y advertencias.
3. Verificar que no se haya roto funcionalidad existente.
4. Incluir pruebas de autorización y aislamiento cuando aplique.
5. Resumir cambios, archivos afectados y verificaciones realizadas.
6. Señalar limitaciones y tareas pendientes.
7. Registrar en Engram decisiones o aprendizajes relevantes para futuras sesiones.

Los agentes deben proponer cambios al documento maestro, no modificarlo automáticamente. Solo actualizarlo cuando el usuario lo apruebe.

---

## 18. Engram

Engram será utilizado como memoria persistente del proyecto.

Debe utilizarse para conservar:
- Decisiones arquitectónicas.
- Problemas importantes y sus soluciones verificadas.
- Preferencias de desarrollo.
- Contexto necesario en sesiones futuras.
- Decisiones que no deban redescubrirse repetidamente.

Engram no reemplaza este documento, los archivos del repositorio ni el código.

### Fuente de verdad

Cuando exista una contradicción:
1. Código actual y comportamiento verificable, para describir lo que existe.
2. Documentación explícita y vigente del proyecto, para describir lo aprobado.
3. Decisiones recientes y justificadas.
4. Memoria de Engram.

Si el código contradice una decisión aprobada, no asumir que el código cambió la decisión: señalar la discrepancia y pedir criterio cuando afecte el comportamiento o arquitectura.

Las memorias de Engram deben actualizarse cuando una decisión importante cambie.

---

## 19. Estado inicial del proyecto

El proyecto comienza desde cero, salvo que una inspección del repositorio indique lo contrario.

Antes de implementar funcionalidades:
- Analizar el entorno.
- Verificar versiones.
- Revisar configuración.
- Definir estructura inicial.
- Diseñar el modelo multi-tenant.
- Definir el contexto de barbería y membresías.
- Definir reglas de autorización y aislamiento.
- Definir fases de desarrollo.

No comenzar creando todas las funcionalidades simultáneamente.

No afirmar que una herramienta, dependencia o servicio está instalado hasta verificarlo.

---

## 20. Orden general de desarrollo

El orden propuesto es:

### Fase 1 — Descubrimiento y arquitectura
- Inspeccionar entorno y versiones.
- Confirmar compatibilidad de Laravel, PHP y PostgreSQL.
- Definir estrategia multi-tenant.
- Definir entidades principales, pertenencia de usuarios y contexto activo.
- Documentar decisiones y riesgos.

### Fase 2 — Base de la plataforma y autenticación
- Configuración inicial.
- Autenticación.
- Usuarios.
- Barberías.
- Membresías y roles.
- Resolución segura del tenant.
- Pruebas de acceso y aislamiento.

### Fase 3 — Operación de barbería
- Clientes.
- Barberos y personal.
- Servicios.
- Horarios y disponibilidad.

### Fase 4 — Citas y agenda
- Creación y consulta de citas.
- Validación de disponibilidad.
- Cancelación y reprogramación.
- Prevención de solapamientos.
- Pruebas de concurrencia y permisos según alcance.

### Fase 5 — Experiencia del cliente e historial
- Páginas públicas de barberías.
- Flujo de reserva.
- Perfil y preferencias.
- Historial de cortes y servicios con privacidad por tenant.

### Fase 6 — Recomendaciones basadas en reglas
- Catálogo de estilos.
- Cuestionario capilar.
- Reglas explicables.
- Presentación de sugerencias y posibilidad de corrección.

### Fase 7 — Computer Vision
- Evaluar herramientas locales.
- Captura y calidad de imagen.
- Landmarks y proporciones.
- Estimación facial aproximada.
- Pruebas y comunicación de limitaciones.

### Fase 8 — Integración y refinamiento
- Integrar recomendador con experiencia de cliente y flujo del barbero.
- Revisar accesibilidad, seguridad, privacidad y rendimiento.
- Pruebas integrales.

### Fase 9 — Preparación comercial
- Definir planes y límites.
- Administrar suscripciones.
- Evaluar pagos y facturación.
- Soporte y configuración de barberías.
- Preparar despliegue y operación.

### Fase 10 — PWA
- Manifest e instalación.
- Estrategia de caché.
- Actualizaciones.
- Evaluar cuidadosamente capacidades offline y sincronización.

El orden puede cambiar si existe una razón técnica clara, pero cualquier cambio importante debe explicarse y registrarse.

---

## 21. Decisiones pendientes

Los siguientes puntos requieren definición antes de implementar las partes correspondientes:

1. **Membresías:** si una cuenta puede pertenecer a varias barberías y cómo se aceptan invitaciones.
2. **Clientes:** qué información será global y qué información será específica de cada barbería.
3. **Historial:** qué puede ver un cliente, qué puede ver cada barbería y cómo se gestiona el consentimiento para compartir información.
4. **Contexto del tenant:** cómo se selecciona la barbería activa y cómo se representa en rutas y sesión.
5. **Registro de negocios:** si cualquier persona puede registrar una barbería o si requiere aprobación.
6. **Planes comerciales:** funcionalidades, límites, periodo de prueba y pagos; no asumirlos todavía.
7. **Personalización:** qué elementos visuales y de configuración podrá modificar cada barbería.
8. **Computer Vision:** herramienta local, dispositivo de procesamiento, retención de resultados y evaluación de precisión.

Los agentes deben señalar cuáles de estas decisiones bloquean la tarea actual y evitar resolverlas silenciosamente.

---

## Regla fundamental

**BarberHub debe ser un proyecto sencillo, funcional, seguro, explicable y mantenible antes de intentar hacerlo sofisticado.**

Debe poder servir a varias barberías sin mezclar sus datos y ofrecer una experiencia clara a cada tipo de usuario.

No agregar complejidad simplemente porque una tecnología sea interesante. Cada decisión técnica debe responder a una necesidad real del producto y a una fase concreta de desarrollo.
