# Proyecto de Modernización v2

Este directorio contiene la versión modernizada de la aplicación, implementando una arquitectura moderna basada en microservicios y micro-frontends.

## Estructura del Proyecto

- **api-core** - Núcleo central de la API implementado con TypeScript/NestJS
  - API Gateway y orquestación de servicios
  - Gestión de autenticación y autorización
  - Implementación de lógica de negocio central

- **file-service** - Servicio de gestión de archivos implementado en Go
  - Validación segura de archivos
  - Transformación y optimización de imágenes
  - Almacenamiento eficiente en S3/MinIO

- **media-processor** - Procesador avanzado de medios implementado en Rust
  - Análisis forense de imágenes
  - Procesamiento de alta performance
  - Detección de contenido prohibido

- **frontend-legacy** - Implementación React tradicional del frontend
  - Soporte para temas clásicos
  - Interfaz responsiva
  - Componentes React reutilizables

- **frontend-modern** - Implementación avanzada con arquitectura moderna
  - Gestión de estado con Redux Toolkit
  - Integración con Web Components
  - Optimización avanzada de rendimiento

- **microfrontends** - Implementación completa de micro-frontends
  - shell - Aplicación principal que orquesta los micro-frontends
  - board-viewer - Visualización de tablones e hilos
  - catalog-viewer - Vista de catálogo
  - post-creator - Creación de posts
  - auth - Gestión de autenticación
  - media-viewer - Visualización de medios
  - moderation - Herramientas de moderación

- **api-specs** - Definiciones de API en formato OpenAPI/Swagger
  - Documentación completa de endpoints
  - Especificaciones para generación de clientes

- **docs** - Documentación técnica
  - Arquitectura y diseño
  - Modelos de seguridad

- **infrastructure** - Configuración de infraestructura
  - Docker y Kubernetes
  - CI/CD
  - Scripts de automatización

## Tecnologías Principales

- Backend: TypeScript (NestJS), Go, Rust
- Frontend: React, TypeScript, Web Components
- Almacenamiento: PostgreSQL, Redis, S3/MinIO
- Infraestructura: Docker, Kubernetes

## Desarrollo

Cada componente tiene su propio README con instrucciones específicas para desarrollo, pruebas y despliegue.

```bash
# Para ejecutar el stack completo en desarrollo
cd infrastructure
docker-compose up
```