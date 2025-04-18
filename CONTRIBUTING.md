# Guía de Contribución

¡Gracias por tu interés en contribuir al proyecto de modernización de 4chan! Este documento proporciona lineamientos para contribuir efectivamente al proyecto.

## Tabla de Contenidos

- [Código de Conducta](#código-de-conducta)
- [Cómo Contribuir](#cómo-contribuir)
  - [Reportando Bugs](#reportando-bugs)
  - [Solicitando Características](#solicitando-características)
  - [Pull Requests](#pull-requests)
- [Estándares de Código](#estándares-de-código)
  - [JavaScript/TypeScript](#javascripttypescript)
  - [CSS/SCSS](#cssscss)
  - [Pruebas](#pruebas)
- [Proceso de Desarrollo](#proceso-de-desarrollo)
  - [Branching Strategy](#branching-strategy)
  - [Commit Messages](#commit-messages)
  - [Revisión de Código](#revisión-de-código)
- [Configuración del Entorno de Desarrollo](#configuración-del-entorno-de-desarrollo)
- [Recursos Adicionales](#recursos-adicionales)

## Código de Conducta

Este proyecto y todos sus participantes están gobernados por nuestro [Código de Conducta](CODE_OF_CONDUCT.md). Al participar, se espera que cumplas con este código. Por favor, reporta comportamientos inaceptables a [conduct@example.org].

## Cómo Contribuir

### Reportando Bugs

Los bugs se reportan a través de [GitHub Issues](https://github.com/username/4chan/issues). Antes de crear un nuevo reporte de bug, por favor verifica si ya existe uno similar.

Cuando reportes un bug, incluye:
- Un título claro y descriptivo
- Pasos para reproducir el problema
- Comportamiento esperado vs. comportamiento actual
- Capturas de pantalla (si aplica)
- Información del entorno (sistema operativo, navegador, versiones)

### Solicitando Características

Las solicitudes de características también se manejan a través de GitHub Issues. Proporciona:
- Descripción clara del problema que la característica resolvería
- Explicación de cómo ayudaría a los usuarios
- Cualquier referencia o ejemplo de implementaciones similares

### Pull Requests

1. Bifurca (fork) el repositorio y crea tu rama desde `main`
2. Si añades código, añade pruebas que cubran tu código
3. Si cambias APIs, actualiza la documentación
4. Asegúrate de que las pruebas pasen
5. Asegúrate de que tu código cumpla con nuestros estándares
6. Envía tu Pull Request!

## Estándares de Código

### JavaScript/TypeScript

- Seguimos el estándar de estilo [Airbnb JavaScript Style Guide](https://github.com/airbnb/javascript)
- Utilizamos TypeScript para todo el nuevo código
- Se requiere documentación JSDoc para todas las funciones públicas

### CSS/SCSS

- Usamos BEM (Block Element Modifier) para nomenclatura de clases
- Preferimos SCSS sobre CSS plano
- Mantenemos la especificidad al mínimo

### Pruebas

- Se requiere cobertura de pruebas para todo el código nuevo (objetivo: >80%)
- Usamos Jest para pruebas unitarias
- Cypress para pruebas end-to-end
- Las pruebas deben ser legibles y mantenibles

## Proceso de Desarrollo

### Branching Strategy

Seguimos un modelo basado en GitFlow:
- `main`: Código en producción
- `develop`: Rama principal de desarrollo
- `feature/*`: Para nuevas características
- `bugfix/*`: Para correcciones de bugs
- `release/*`: Para preparación de lanzamientos
- `hotfix/*`: Para arreglos urgentes en producción

### Commit Messages

Seguimos el formato de [Conventional Commits](https://www.conventionalcommits.org/):

```
<tipo>[alcance opcional]: <descripción>

[cuerpo opcional]

[pie opcional]
```

Tipos principales:
- `feat`: Nueva característica
- `fix`: Corrección de bug
- `docs`: Cambios en documentación
- `style`: Cambios que no afectan el significado del código
- `refactor`: Cambio de código que no arregla un bug ni añade una característica
- `test`: Adición o corrección de pruebas
- `chore`: Cambios en el proceso de build o herramientas auxiliares

### Revisión de Código

- Cada PR requiere al menos una aprobación
- Los comentarios deben ser constructivos y claros
- Se espera que los autores respondan a los comentarios de manera oportuna

## Configuración del Entorno de Desarrollo

1. Clona el repositorio
2. Instala las dependencias con `npm install`
3. Copia `.env.example` a `.env` y configura las variables
4. Inicia el entorno de desarrollo con `npm run dev`
5. Ejecuta las pruebas con `npm test`

Para configurar la base de datos:
1. Instala PostgreSQL y Redis
2. Ejecuta las migraciones con `npm run db:migrate`
3. (Opcional) Carga datos de prueba con `npm run db:seed`

## Recursos Adicionales

- [Documentación de la API](docs/api.md)
- [Guía de Arquitectura](docs/architecture.md)
- [Plan de Migración](docs/migration.md)

---

¡Gracias por contribuir a la modernización de 4chan! Tus esfuerzos ayudan a preservar y mejorar una parte importante de la cultura de internet.