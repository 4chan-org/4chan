# API Core

Núcleo central de la API que proporciona servicios principales para la aplicación. Implementa una arquitectura de microservicios con múltiples servicios especializados.

## Estructura del Proyecto

- **services/** - Servicios especializados
  - **core-service/** - Servicio central (TypeScript/NestJS)
  - **file-service/** - Servicio de gestión de archivos (Go)
  - **media-processor/** - Procesador avanzado de medios (Rust)
  - **nodejs-service/** - Servicios auxiliares (Node.js)
  - **go-service/** - Servicios auxiliares (Go)
  - **rust-service/** - Servicios auxiliares (Rust)

- **shared/** - Recursos compartidos entre servicios
  - **api-specs/** - Especificaciones OpenAPI
  - **schemas/** - Esquemas compartidos
  - **utils/** - Utilidades comunes

- **src/** - Código fuente principal del API Core
  - **app.ts** - Punto de entrada de la aplicación
  - **config/** - Configuraciones
  - **controllers/** - Controladores HTTP
  - **middleware/** - Middleware para procesamiento de solicitudes
  - **models/** - Modelos de datos
  - **services/** - Servicios de negocio
  - **utils/** - Utilidades

## Tecnologías Principales

- TypeScript (NestJS) - Servicio principal
- Go - Servicios de alto rendimiento
- Rust - Procesamiento de medios y seguridad
- PostgreSQL - Almacenamiento de datos principal
- Redis - Caché y almacenamiento en memoria
- S3/MinIO - Almacenamiento de archivos

## Instalación y Desarrollo

```bash
# Instalar dependencias
npm install

# Desarrollo
npm run dev

# Tests
npm run test

# Build
npm run build
```

## Servicios y Comunicación

Los servicios se comunican entre sí mediante:
- API REST con especificaciones OpenAPI
- gRPC para comunicación de alta performance entre servicios internos
- Mensajería asíncrona para operaciones en segundo plano

## Arquitectura de Seguridad

- Autenticación con JWT
- Autorización basada en roles
- Validación de entrada
- Sanitización de datos
- Rate limiting
- Protección contra ataques comunes (XSS, CSRF, etc.)