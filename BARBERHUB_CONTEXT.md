# BarberHub — Contexto Maestro del Proyecto

> Este documento contiene las reglas, decisiones y contexto fundamental de BarberHub.
> Debe considerarse una fuente de verdad del proyecto.
>
> Los agentes deben leer este documento antes de realizar cambios importantes.
> Las decisiones aquí establecidas no deben modificarse silenciosamente.

---

## 1. Identidad del proyecto

**Nombre:** BarberHub

**Tipo:** Plataforma web para la administración de una barbería.

**Propósito académico:** Proyecto para la materia Desarrollo de Aplicaciones Web y Móviles.

El proyecto debe demostrar buenas prácticas de desarrollo, arquitectura MVC y el uso justificado del patrón Singleton, sin introducir complejidad innecesaria únicamente para cumplir requisitos académicos.

---

## 2. Objetivo general

Crear una plataforma que permita administrar las operaciones principales de una barbería y, posteriormente, ofrecer recomendaciones personalizadas de cortes de cabello utilizando información del cliente, historial de cortes y análisis aproximado de características faciales mediante Computer Vision.

El sistema debe complementar el criterio profesional del barbero, no sustituirlo.

---

## 3. Stack tecnológico

### Backend

* PHP
* Laravel

### Base de datos

* PostgreSQL

### Frontend

* Blade
* Tailwind CSS
* JavaScript

### Control de versiones

* Git
* GitHub

### Futuro

* PWA
* Computer Vision
* Posiblemente Python para componentes específicos de análisis facial

---

## 4. Arquitectura

La arquitectura principal debe seguir el patrón:

**MVC — Model View Controller**

Las responsabilidades deben mantenerse separadas.

### Model

Responsable de:

* Representar entidades y datos.
* Relaciones entre entidades.
* Interacción con la base de datos cuando corresponda.

### View

Responsable de:

* Presentación.
* Interfaz de usuario.
* Formularios.
* Visualización de información.

### Controller

Responsable de:

* Recibir solicitudes.
* Validar y coordinar operaciones.
* Utilizar modelos y servicios.
* Devolver respuestas o vistas.

Evitar colocar lógica de negocio compleja directamente en las vistas.

Evitar colocar grandes cantidades de lógica de negocio dentro de los controladores.

Cuando una lógica sea suficientemente compleja, evaluar utilizar servicios u otras capas apropiadas.

---

## 5. Patrón Singleton

BarberHub debe utilizar el patrón Singleton porque forma parte de los requisitos académicos.

Sin embargo:

**No debe utilizarse Singleton únicamente para demostrar que se utilizó.**

Antes de implementar un Singleton, debe existir una justificación técnica clara.

El agente debe:

1. Identificar dónde tendría sentido utilizarlo.
2. Explicar qué problema resuelve.
3. Evaluar si realmente es necesario.
4. Evitar utilizar Singleton en clases donde la inyección de dependencias sea una solución más apropiada.

La implementación debe mantenerse sencilla y comprensible.

---

## 6. Usuarios y roles

El sistema debe contemplar inicialmente tres roles:

### Administrador

Responsabilidades:

* Administrar usuarios.
* Administrar barberos.
* Administrar servicios.
* Administrar horarios.
* Consultar información general de la barbería.

### Barbero

Responsabilidades:

* Consultar agenda.
* Consultar clientes.
* Registrar servicios realizados.
* Registrar características del cabello.
* Registrar observaciones.
* Consultar recomendaciones.

### Cliente

Responsabilidades:

* Administrar sus datos.
* Consultar sus citas.
* Consultar su historial.
* Consultar recomendaciones.
* Proporcionar información sobre sus preferencias.

---

## 7. Módulos principales

BarberHub deberá desarrollarse progresivamente.

### Usuarios

* Autenticación.
* Roles.
* Permisos.

### Clientes

* Información personal.
* Información del cabello.
* Preferencias.
* Historial.
* Citas.

### Barberos

* Perfil.
* Disponibilidad.
* Agenda.
* Historial de servicios realizados.

### Servicios

Cada servicio puede contener:

* Nombre.
* Descripción.
* Precio.
* Duración.
* Estado.

### Citas

Debe permitir:

* Crear citas.
* Consultar disponibilidad.
* Asociar cliente.
* Asociar barbero.
* Asociar servicio.
* Definir fecha y hora.
* Cambiar estado.

### Historial de cortes

Debe registrar información como:

* Fecha.
* Barbero.
* Tipo de corte.
* Características del cabello.
* Longitud.
* Estilo.
* Observaciones.

El historial debe utilizarse posteriormente para mejorar la personalización de las recomendaciones.

---

# 8. Sistema de recomendaciones

Esta es una de las características diferenciadoras de BarberHub.

El sistema deberá recomendar estilos de corte considerando múltiples factores.

## Factores faciales

Se podrá utilizar Computer Vision para estimar aproximadamente la forma del rostro.

Las categorías iniciales pueden incluir:

* Ovalado.
* Redondo.
* Cuadrado.
* Rectangular.
* Corazón.
* Diamante.

La clasificación será aproximada.

Nunca debe presentarse como una medición médica ni como una verdad absoluta.

Debe utilizarse lenguaje como:

> "Forma facial estimada: ovalada."

en lugar de afirmaciones absolutas.

---

## 9. Computer Vision

Inicialmente se busca utilizar Computer Vision local.

La idea inicial es utilizar landmarks faciales para obtener proporciones aproximadas del rostro.

El sistema podrá analizar características como:

* Ancho del rostro.
* Alto del rostro.
* Proporciones generales.
* Línea de mandíbula.
* Frente.
* Pómulos.

El objetivo es obtener una clasificación aproximada de la forma facial.

### Restricciones

Inicialmente:

* No entrenar modelos propios.
* No utilizar Machine Learning innecesario.
* No utilizar IA generativa para recomendar cortes.
* No depender de APIs de IA externas de pago.
* Priorizar procesamiento local cuando sea viable.

---

## 10. Información del cabello

El sistema también debe considerar información proporcionada por el cliente o barbero.

Características posibles:

* Tipo de cabello.
* Textura.
* Grosor.
* Densidad.
* Longitud.
* Facilidad de peinado.
* Tiempo disponible para mantenimiento.
* Preferencias de estilo.
* Estilos que no desea.

---

## 11. Motor de recomendaciones

Inicialmente será un sistema basado en reglas.

Las reglas deberán poder explicar por qué se recomienda un determinado estilo.

Ejemplo conceptual:

**Forma facial:** ovalada
**Cabello:** grueso y ondulado
**Mantenimiento deseado:** bajo

Resultado:

> "Este estilo es compatible con la forma facial estimada y con las características del cabello. Además, requiere un nivel de mantenimiento bajo."

Las recomendaciones deben ser explicables.

No implementar un sistema complejo de Machine Learning si las reglas son suficientes para el MVP.

---

## 12. Privacidad

Las fotografías faciales deben tratarse como información sensible dentro del diseño del sistema.

El proyecto debe minimizar la información almacenada.

Cuando sea posible:

* Procesar localmente.
* Evitar almacenar fotografías innecesariamente.
* Separar los datos utilizados para análisis de los datos personales.
* Explicar claramente al usuario qué información se utiliza.

No implementar almacenamiento permanente de fotografías faciales sin una justificación clara.

---

## 13. PWA

La conversión a PWA será una fase posterior.

La primera versión debe ser una aplicación web funcional.

Desde el diseño inicial se debe evitar tomar decisiones que dificulten posteriormente la conversión a PWA.

No implementar funcionalidades PWA prematuramente.

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

Las entradas del usuario deben validarse.

Nunca confiar directamente en datos proporcionados por el cliente.

### Consistencia

Seguir las convenciones de Laravel siempre que sean adecuadas.

### Cambios pequeños

Preferir cambios pequeños, verificables y fáciles de revertir.

---

## 15. Dependencias

No instalar una dependencia nueva sin evaluar primero:

1. Si realmente es necesaria.
2. Si Laravel ya proporciona una solución.
3. Si existe una alternativa más sencilla.
4. Qué impacto tendrá en el proyecto.

Las dependencias deben mantenerse al mínimo razonable.

---

## 16. Git

Git debe utilizarse durante todo el desarrollo.

Los commits deben ser:

* Pequeños.
* Relacionados con un cambio lógico.
* Descriptivos.

No realizar commits gigantes que mezclen funcionalidades no relacionadas.

Antes de realizar cambios importantes, revisar el estado actual del repositorio.

No sobrescribir trabajo existente sin analizarlo primero.

---

## 17. Reglas para agentes

Antes de modificar código:

1. Leer `BARBERHUB_CONTEXT.md`.
2. Revisar el estado actual del repositorio.
3. Revisar las decisiones relevantes almacenadas en Engram.
4. Comprender el objetivo de la tarea.
5. Identificar archivos afectados.
6. Explicar brevemente el plan cuando el cambio sea significativo.

Durante la implementación:

* No modificar archivos no relacionados sin justificación.
* No cambiar decisiones arquitectónicas silenciosamente.
* No agregar funcionalidades no solicitadas.
* No introducir dependencias innecesarias.
* Mantener los cambios enfocados.

Después de implementar:

1. Ejecutar pruebas relevantes.
2. Revisar errores.
3. Verificar que no se haya roto funcionalidad existente.
4. Resumir los cambios.
5. Registrar en Engram las decisiones o aprendizajes que sean relevantes para futuras sesiones.

---

## 18. Engram

Engram será utilizado como memoria persistente del proyecto.

Debe utilizarse para conservar:

* Decisiones arquitectónicas.
* Problemas importantes y sus soluciones.
* Preferencias de desarrollo.
* Contexto que pueda ser necesario en sesiones futuras.
* Decisiones que no deban redescubrirse repetidamente.

Engram NO reemplaza este documento.

### Fuente de verdad

Cuando exista una contradicción:

1. Código actual y comportamiento verificable.
2. Documentación explícita del proyecto.
3. Decisiones recientes y justificadas.
4. Memoria de Engram.

Las memorias de Engram deben actualizarse cuando una decisión importante cambie.

---

## 19. Estado inicial del proyecto

El proyecto comienza desde cero.

Antes de implementar funcionalidades:

* Analizar el entorno.
* Verificar versiones.
* Revisar configuración.
* Definir estructura inicial.
* Definir modelo de datos.
* Definir fases.

No comenzar creando todas las funcionalidades simultáneamente.

---

## 20. Orden general de desarrollo

El proyecto debe avanzar aproximadamente en este orden:

### Fase 1

Configuración y arquitectura inicial.

### Fase 2

Usuarios, autenticación y roles.

### Fase 3

Clientes, barberos y servicios.

### Fase 4

Citas y agenda.

### Fase 5

Historial de cortes.

### Fase 6

Sistema de recomendaciones basado en reglas.

### Fase 7

Computer Vision.

### Fase 8

Integración completa del recomendador.

### Fase 9

Pruebas, seguridad y optimización.

### Fase 10

Conversión a PWA.

El orden puede cambiar si existe una razón técnica clara, pero cualquier cambio importante debe ser explicado y registrado.

---

# Regla fundamental

**BarberHub debe ser un proyecto sencillo, funcional, explicable y mantenible antes de intentar hacerlo sofisticado.**

No agregar complejidad simplemente porque una tecnología sea interesante.

Cada decisión técnica debe responder a una necesidad real del proyecto.
